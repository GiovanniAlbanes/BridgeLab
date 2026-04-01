<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Console\Command;
use PDO;

class ImportDb3 extends Command
{
    protected $signature = 'import:db3 {path : Percorso assoluto al file .db3}
                                       {--fresh : Elimina i giochi già importati con lo stesso nome prima di reimportare}';

    protected $description = 'Importa giochi, domande e risposte da un file SQLite .db3';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File non trovato: $path");
            return 1;
        }

        $pdo = new PDO("sqlite:$path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // ── Games ──────────────────────────────────────────────────────────────
        $srcGames = $pdo->query('SELECT * FROM games ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        $this->info('Trovati ' . count($srcGames) . ' gioco/i, importazione in corso…');

        $gameMap    = []; // src id → new id
        $questionMap = []; // src id → new id

        foreach ($srcGames as $sg) {
            if ($this->option('fresh')) {
                Game::where('name', $sg['name'])->each(function (Game $g) {
                    foreach ($g->questions as $q) {
                        if ($q->media_path) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($q->media_path);
                        }
                    }
                    $g->delete();
                });
            }

            $game          = Game::create(['name' => $sg['name']]);
            $gameMap[$sg['id']] = $game->id;
            $this->line("  ✓ Gioco: {$sg['name']} (id {$game->id})");
        }

        // ── Questions ──────────────────────────────────────────────────────────
        $srcQs = $pdo->query('SELECT * FROM questions ORDER BY game_id, ord')->fetchAll(PDO::FETCH_ASSOC);

        $this->info('Trovate ' . count($srcQs) . ' domande…');

        foreach ($srcQs as $sq) {
            if (! isset($gameMap[$sq['game_id']])) {
                $this->warn("  ⚠ Domanda {$sq['id']}: game_id {$sq['game_id']} non trovato, salto.");
                continue;
            }

            [$mediaPath, $mediaType] = $this->resolveMedia($sq);

            $question = Question::create([
                'game_id'    => $gameMap[$sq['game_id']],
                'text'       => $sq['questionText'],
                'order'      => (int) $sq['ord'],
                'media_path' => $mediaPath,
                'media_type' => $mediaType,
            ]);

            $questionMap[$sq['id']] = $question->id;
        }

        // ── Answers ────────────────────────────────────────────────────────────
        $srcAs = $pdo->query('SELECT * FROM answers ORDER BY question_id, ord')->fetchAll(PDO::FETCH_ASSOC);

        $this->info('Trovate ' . count($srcAs) . ' risposte…');

        foreach ($srcAs as $sa) {
            if (! isset($questionMap[$sa['question_id']])) {
                $this->warn("  ⚠ Risposta {$sa['id']}: question_id {$sa['question_id']} non trovato, salto.");
                continue;
            }

            // Normalize value: positive → 1.0 (corretta), negative/zero → -0.25 (sbagliata)
            $value = (float) $sa['value'] > 0 ? 1.0 : -0.25;

            Answer::create([
                'question_id' => $questionMap[$sa['question_id']],
                'text'        => $sa['answer'],
                'value'       => $value,
                'order'       => (int) $sa['ord'],
            ]);
        }

        $this->newLine();
        $this->info('✅ Import completato.');
        $this->table(
            ['Entità', 'Importati'],
            [
                ['Giochi',   count($gameMap)],
                ['Domande',  count($questionMap)],
                ['Risposte', count($srcAs)],
            ]
        );

        // Remind about media files
        $hasMedia = collect($srcQs)->filter(fn ($q) => $q['videoUrls'] || $q['imageUrls'])->count();
        if ($hasMedia) {
            $this->newLine();
            $this->warn("⚠  {$hasMedia} domande hanno file media referenziati.");
            $this->line('   Copia i file in: storage/app/public/media/');
            $this->line('   I percorsi sono stati salvati così come appaiono nel .db3.');
        }

        return 0;
    }

    private function resolveMedia(array $q): array
    {
        // videoUrls takes priority over imageUrls
        $raw  = $q['videoUrls'] ?: $q['imageUrls'];
        $type = $q['videoUrls'] ? 'video' : ($q['imageUrls'] ? 'image' : null);

        if (! $raw || ! $type) {
            return [null, null];
        }

        // Strip JSON array wrapper → get first filename
        $decoded = json_decode($raw, true);
        $file    = is_array($decoded) ? ($decoded[0] ?? null) : trim($raw, '[]"');

        if (! $file) {
            return [null, null];
        }

        return [$file, $type];
    }
}
