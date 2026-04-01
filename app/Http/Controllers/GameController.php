<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Game;
use App\Models\Question;
use App\Events\ProjectionUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GameController extends Controller
{
    private const ACTIVE_KEY          = 'bl:active_question';
    private const SELECTED_ANSWER_KEY = 'bl:selected_answer';
    private const WINNER_COLOR_KEY    = 'bl:winner_color';

    // ── Games ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View|JsonResponse
    {
        $games = Game::withCount('questions')->latest()->get();
        if ($request->wantsJson()) {
            return response()->json(['games' => $games]);
        }
        return view('games.index', ['games' => $games]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $game = Game::create($data);
        return response()->json(['ok' => true, 'game' => $game]);
    }

    public function show(Game $game): View
    {
        return view('games.edit', ['game' => $game->load('questions.answers')]);
    }

    public function updateGame(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $game->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroyGame(Game $game): JsonResponse
    {
        // Remove media files
        foreach ($game->questions as $q) {
            if ($q->media_path) Storage::disk('public')->delete($q->media_path);
        }
        $game->delete();
        return response()->json(['ok' => true]);
    }

    // ── Questions ─────────────────────────────────────────────────────────────

    public function questionsJson(Game $game): JsonResponse
    {
        $questions = $game->questions()->with('answers')->orderBy('order')->get()->map(fn ($q) => [
            'id'      => $q->id,
            'text'    => $q->text,
            'order'   => $q->order,
            'answers' => $q->answers->map(fn ($a) => [
                'id'    => $a->id,
                'text'  => $a->text,
                'value' => (float) $a->value,
            ]),
        ]);
        return response()->json(['questions' => $questions]);
    }

    public function storeQuestion(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'text'  => ['required', 'string'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:2097152'],
        ]);

        $order = $game->questions()->max('order') + 1;

        $question = $game->questions()->create([
            'text'  => $data['text'],
            'order' => $order,
        ]);

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $path = $file->store('media', 'public');
            $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
            $question->update(['media_path' => $path, 'media_type' => $type]);
        }

        return response()->json(['ok' => true, 'question' => $question->load('answers')]);
    }

    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'text'         => ['required', 'string'],
            'media'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:2097152'],
            'remove_media' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_media') && $question->media_path) {
            Storage::disk('public')->delete($question->media_path);
            $question->update(['media_path' => null, 'media_type' => null]);
        }

        if ($request->hasFile('media')) {
            if ($question->media_path) Storage::disk('public')->delete($question->media_path);
            $file = $request->file('media');
            $path = $file->store('media', 'public');
            $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
            $question->update(['media_path' => $path, 'media_type' => $type]);
        }

        $question->update(['text' => $data['text']]);

        return response()->json(['ok' => true, 'question' => $question->fresh()->load('answers')]);
    }

    public function destroyQuestion(Question $question): JsonResponse
    {
        if ($question->media_path) Storage::disk('public')->delete($question->media_path);
        $question->delete();
        return response()->json(['ok' => true]);
    }

    public function reorderQuestions(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        foreach ($data['order'] as $pos => $id) {
            $game->questions()->where('id', $id)->update(['order' => $pos]);
        }
        return response()->json(['ok' => true]);
    }

    // ── Answers ───────────────────────────────────────────────────────────────

    public function storeAnswer(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'text'  => ['required', 'string', 'max:200'],
            'value' => ['required', 'numeric'],
        ]);
        $data['order'] = $question->answers()->max('order') + 1;
        $answer = $question->answers()->create($data);
        return response()->json(['ok' => true, 'answer' => $answer]);
    }

    public function updateAnswer(Request $request, Answer $answer): JsonResponse
    {
        $data = $request->validate([
            'text'  => ['required', 'string', 'max:200'],
            'value' => ['required', 'numeric'],
        ]);
        $answer->update($data);
        return response()->json(['ok' => true, 'answer' => $answer]);
    }

    public function destroyAnswer(Answer $answer): JsonResponse
    {
        $answer->delete();
        return response()->json(['ok' => true]);
    }

    public function moveQuestion(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'game_id'  => ['required', 'integer', 'exists:games,id'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $targetGameId = $data['game_id'];
        $insertOrder  = $data['position'] ?? $question->order;

        // Make room at the target position
        Question::where('game_id', $targetGameId)
            ->where('order', '>=', $insertOrder)
            ->increment('order');

        $question->update(['game_id' => $targetGameId, 'order' => $insertOrder]);

        return response()->json(['ok' => true]);
    }


    // ── Projection ────────────────────────────────────────────────────────────

    public function setActiveQuestion(Request $request): JsonResponse
    {
        $data = $request->validate(['question_id' => ['nullable', 'integer']]);
        $id   = $data['question_id'] ?? null;
        Cache::forever(self::ACTIVE_KEY, $id);
        Cache::forever(self::SELECTED_ANSWER_KEY, null);

        $this->broadcastProjection();

        return response()->json(['ok' => true]);
    }

    public function activeQuestion(): JsonResponse
    {
        $id       = Cache::get(self::ACTIVE_KEY);
        $question = $id ? Question::with('answers')->find($id) : null;

        $selId          = Cache::get(self::SELECTED_ANSWER_KEY);
        $selectedAnswer = $selId ? $this->formatSelectedAnswer($selId) : null;

        return response()->json([
            'question'        => $question ? $this->formatQuestion($question) : null,
            'selected_answer' => $selectedAnswer,
            'winner_color'    => Cache::get(self::WINNER_COLOR_KEY, 'yellow'),
        ]);
    }

    public function setSelectedAnswer(Request $request): JsonResponse
    {
        $data = $request->validate(['answer_id' => ['nullable', 'integer']]);
        $selId = $data['answer_id'] ?? null;
        Cache::forever(self::SELECTED_ANSWER_KEY, $selId);

        $this->broadcastProjection();

        return response()->json(['ok' => true]);
    }

    public function setWinnerColor(Request $request): JsonResponse
    {
        $data = $request->validate(['color' => ['required', 'string', 'in:yellow,green,blue,red']]);
        Cache::forever(self::WINNER_COLOR_KEY, $data['color']);

        $this->broadcastProjection();

        return response()->json(['ok' => true]);
    }

    public function projectView(): View
    {
        return view('project');
    }

    private function broadcastProjection(): void
    {
        $id       = Cache::get(self::ACTIVE_KEY);
        $question = $id ? Question::with('answers')->find($id) : null;
        $selId    = Cache::get(self::SELECTED_ANSWER_KEY);
        broadcast(new ProjectionUpdated(
            $question ? $this->formatQuestion($question) : null,
            $selId ? $this->formatSelectedAnswer($selId) : null,
            Cache::get(self::WINNER_COLOR_KEY, 'yellow')
        ));
    }

    private function formatSelectedAnswer(int $answerId): ?array
    {
        $ans = Answer::find($answerId);
        return $ans ? ['id' => $ans->id, 'is_correct' => (float) $ans->value > 0] : null;
    }

    private function formatQuestion(Question $q): array
    {
        return [
            'id'         => $q->id,
            'text'       => $q->text,
            'media_url'  => $q->media_path ? '/storage/' . $q->media_path : null,
            'media_type' => $q->media_type,
            'answers'    => $q->answers->map(fn ($a) => [
                'id'    => $a->id,
                'text'  => $a->text,
                'value' => $a->value,
            ]),
        ];
    }
}
