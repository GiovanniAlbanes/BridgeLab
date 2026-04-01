<?php

namespace App\Http\Controllers;

use App\Events\BridgeCommandDispatched;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use App\Events\StateUpdated;

class BridgeLabController extends Controller
{
    private const STATE_KEY     = 'bl:state';
    private const HEARTBEAT_KEY = 'bl:heartbeat';
    private const TEAMS_KEY     = 'bl:teams';
    private const APP_DIR       = 'C:\\laragon\\www\\bridge-test';
    private const BRIDGE_DIR    = 'C:\\laragon\\www\\bridge-test\\BridgeLab\\bin\\x64\\Debug\\net8.0';
    private const BRIDGE_EXE    = 'C:\\laragon\\www\\bridge-test\\BridgeLab\\bin\\x64\\Debug\\net8.0\\BridgeLab.exe';



    // ─── Web ──────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('bridge-host');
    }

    public function state(): JsonResponse
    {
        $hb       = Cache::get(self::HEARTBEAT_KEY);
        $lastSeen = data_get($hb, 'received_at');

        return response()->json([
            'bridge_online' => $this->isBridgeOnline($lastSeen),
            'last_seen_at'  => $lastSeen,
            'state'         => $this->getState(),
            'teams'         => $this->getTeams(),
            'server_time'   => now()->toIso8601String(),
        ]);
    }

    // ─── Bridge endpoints (called by BridgeLab C#) ────────────────────────────

    public function heartbeat(Request $request): JsonResponse
    {
        $lastHeartbeat = Cache::get(self::HEARTBEAT_KEY);
        $wasOnline = $this->isBridgeOnline(data_get($lastHeartbeat, 'received_at'));

        Cache::forever(self::HEARTBEAT_KEY, [
            'received_at' => now()->toIso8601String(),
            'meta'        => $request->only(['bridge_name', 'version', 'serial', 'dmx']),
        ]);
        $this->broadcastState();

        if (! $wasOnline) {
            $this->syncBridgeState();
        }

        return response()->json(['ok' => true]);
    }

    /** Receive a button press from Arduino via BridgeLab */
    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'integer', 'min:1'],
            'pressed' => ['required', 'boolean'],
            'early'   => ['nullable', 'boolean'], // 👈 AGGIUNGI
        ]);

        logger($request->all());

        if (! $data['pressed']) {
            return response()->json(['ok' => true, 'ignored' => 'release']);
        }

        $state   = $this->getState();
        $channel = (int) $data['channel'];

        // Only accept presses when buzzer is open
        if ($state['phase'] !== 'open') {
            return response()->json(['ok' => true, 'ignored' => $state['phase']]);
        }

        $isEarly = $data['early'] ?? false;

        if ($isEarly) {
            if (! collect($state['early_buzzes'] ?? [])->contains('channel', $channel)) {
                $state['early_buzzes'][] = [
                    'channel' => $channel,
                    'at'      => now()->toIso8601String(),
                ];

                $this->saveState($state);
                $this->broadcastState();
            }

            return response()->json(['ok' => true, 'early' => true]);
        }

        // Duplicate channel – ignore
        if (collect($state['buzzes'])->contains('channel', $channel)) {
            return response()->json(['ok' => true, 'ignored' => 'duplicate']);
        }

        $buzz = [
            'channel' => $channel,
            'order'   => count($state['buzzes']) + 1,
            'at'      => now()->toIso8601String(),
        ];

        $state['buzzes'][] = $buzz;

        // First press wins
        if (count($state['buzzes']) === 1) {
            $state['winner'] = $buzz;
            $state['phase']  = 'locked';
            $this->enqueue(['type' => 'buzz_channel', 'channel' => $channel]);
        }

        $this->saveState($state);
        $this->broadcastState();

        return response()->json(['ok' => true, 'buzz' => $buzz]);
    }

    // ─── Bridge launcher ──────────────────────────────────────────────────────

    public function launchBridge(): JsonResponse
    {
        if (! file_exists(self::BRIDGE_EXE)) {
            return response()->json(['ok' => false, 'error' => 'BridgeLab.exe non trovato. Compila prima il progetto BridgeLab.']);
        }

        $this->runDetached('taskkill /F /IM BridgeLab.exe >nul 2>&1');

        // Patch bridgelab.json with the real base_url so BridgeLab.exe
        // reaches Laravel whether served by Laragon or `php artisan serve`.
        $this->patchBridgeConfig(['base_url' => url('/')]);

        // Only start Reverb if port 8080 is not already listening
        $sock = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            // Reverb already running — skip
        } else {
            $phpBin = trim((string) shell_exec('where php.exe 2>nul')) ?: 'php';
            $phpBin = explode("\n", $phpBin)[0];
            $this->runHiddenPowerShell(
                "Start-Process -FilePath '$phpBin' -ArgumentList 'artisan','reverb:start','--host=0.0.0.0' -WorkingDirectory '" . self::APP_DIR . "' -WindowStyle Hidden"
            );
            usleep(1500000); // 1.5s — give Reverb time to bind
        }

        $this->runHiddenPowerShell(
            "Start-Process -FilePath '" . self::BRIDGE_EXE . "' -WorkingDirectory '" . self::BRIDGE_DIR . "' -WindowStyle Hidden"
        );
        try { $this->broadcastState(); } catch (\Throwable) {}

        return response()->json(['ok' => true]);
    }

    public function closeBridge(): JsonResponse
    {
        $this->runDetached('taskkill /F /IM BridgeLab.exe >nul 2>&1');
        try { $this->broadcastState(); } catch (\Throwable) {}

        return response()->json(['ok' => true]);
    }

    // ─── Host endpoints (called by the web UI) ────────────────────────────────

    /** Reset everything: idle phase, no buzzes, all lamps off */
    public function reset(): JsonResponse
    {
        $this->saveState($this->emptyState());
        $this->enqueue(['type' => 'all_off']);
        $this->broadcastState(); // 👈 AGGIUNGI
        return response()->json(['ok' => true]);
    }

    /** Open the buzzer: loop animation starts, ready to accept presses */
    public function open(): JsonResponse
    {
        $state              = $this->getState();
        $state['phase']     = 'open';
        $state['buzzes']    = [];
        $state['early_buzzes'] = [];
        $state['winner']    = null;
        $state['opened_at'] = now()->toIso8601String();
        $state['opened_at_ms'] = now()->getTimestampMs();
        $this->saveState($state);
        $this->enqueue([
            'type'      => 'open_buzzer',
            'opened_at' => $state['opened_at'],
        ]);

        $this->broadcastState(); // 👈 AGGIUNGI

        return response()->json(['ok' => true]);
    }

    /** Correct answer: +1 pt to winner, idle phase, all lamps off */
    public function correct(): JsonResponse
    {
        $state = $this->getState();

        if ($state['phase'] !== 'locked' || ! $state['winner']) {
            return response()->json(['ok' => false, 'error' => 'nessun vincitore']);
        }

        $channel = $state['winner']['channel'];
        $this->adjustScore($channel, +1.0);

        $state['phase']        = 'idle';
        $state['buzzes']       = [];
        $state['early_buzzes'] = [];
        $state['winner']       = null;
        $this->saveState($state);
        $this->enqueue(['type' => 'correct_channel', 'channel' => $channel]);
        $this->broadcastState(); // 👈 AGGIUNGI

        return response()->json(['ok' => true]);
    }

    /** Wrong answer: -0.25 pt to winner, pass to next or reopen buzzer */
    public function wrong(): JsonResponse
    {
        $state = $this->getState();

        if ($state['phase'] !== 'locked' || ! $state['winner']) {
            return response()->json(['ok' => false, 'error' => 'nessun vincitore']);
        }

        $channel = $state['winner']['channel'];
        $this->adjustScore($channel, -0.25);

        // Lampada rossa fissa — aspetta che l'host riapra il buzzer
        $state['phase']        = 'idle';
        $state['buzzes']       = [];
        $state['early_buzzes'] = [];
        $state['winner']       = null;
        $this->saveState($state);
        $this->enqueue(['type' => 'wrong_channel', 'channel' => $channel]);
        $this->broadcastState(); // 👈 AGGIUNGI

        return response()->json(['ok' => true]);
    }

    /** Save team names (from the host UI) */
    public function saveTeams(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teams'           => ['required', 'array'],
            'teams.*.channel' => ['required', 'integer', 'min:1', 'max:8'],
            'teams.*.name'    => ['required', 'string', 'max:50'],
            'teams.*.score'   => ['required', 'numeric'],
        ]);

        Cache::forever(self::TEAMS_KEY, $data['teams']);

        return response()->json(['ok' => true]);
    }

    /** Manual DMX buzz override from host UI */
    public function buzzChannel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'integer', 'min:1'],
        ]);

        $this->enqueue(['type' => 'buzz_channel', 'channel' => (int) $data['channel']]);

        return response()->json(['ok' => true]);
    }

    /** Reset only scores, keep team names */
    public function resetScores(): JsonResponse
    {
        $teams = $this->getTeams();
        foreach ($teams as &$t) {
            $t['score'] = 0.0;
        }
        Cache::forever(self::TEAMS_KEY, $teams);

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getState(): array
    {
        return Cache::get(self::STATE_KEY, $this->emptyState());
    }

    private function saveState(array $state): void
    {
        Cache::forever(self::STATE_KEY, $state);
    }

    private function emptyState(): array
    {
        return [
            'phase'        => 'idle',
            'winner'       => null,
            'buzzes'       => [],
            'early_buzzes' => [],
            'opened_at'    => null,
        ];
    }

    private function getTeams(): array
    {
        return Cache::get(self::TEAMS_KEY, $this->defaultTeams());
    }

    private function defaultTeams(): array
    {
        return array_map(fn ($i) => [
            'channel' => $i,
            'name'    => "Squadra $i",
            'score'   => 0.0,
        ], range(1, 4));
    }

    private function adjustScore(int $channel, float $delta): void
    {
        $teams = $this->getTeams();
        foreach ($teams as &$t) {
            if ($t['channel'] === $channel) {
                $t['score'] = round($t['score'] + $delta, 2);
                break;
            }
        }
        Cache::forever(self::TEAMS_KEY, $teams);
    }

    private function enqueue(array $command): void
    {
        $command['id']        = (string) str()->uuid();
        $command['queued_at'] = now()->toIso8601String();
        broadcast(new BridgeCommandDispatched($command));
    }

    private function syncBridgeState(): void
    {
        $state = $this->getState();

        if ($state['phase'] === 'open') {
            $this->enqueue([
                'type'      => 'open_buzzer',
                'opened_at' => $state['opened_at'],
            ]);
            return;
        }

        if ($state['phase'] === 'locked' && is_array($state['winner'] ?? null)) {
            $channel = (int) data_get($state, 'winner.channel', 0);
            if ($channel > 0) {
                $this->enqueue(['type' => 'buzz_channel', 'channel' => $channel]);
                return;
            }
        }
    }

    /** Merge $patch into bridgelab.json (in the exe directory), preserving other keys. */
    private function patchBridgeConfig(array $patch): void
    {
        $path = self::BRIDGE_DIR . '\\bridgelab.json';
        $existing = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?? [])
            : [];
        $merged = array_merge($existing, $patch);
        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function runDetached(string $command): void
    {
        pclose(popen("start \"\" /B cmd /c \"$command\"", 'r'));
    }

    private function runHiddenPowerShell(string $command): void
    {
        $escaped = str_replace('"', '\"', $command);
        pclose(popen("start \"\" /B powershell -WindowStyle Hidden -Command \"$escaped\"", 'r'));
    }

    private function isBridgeOnline(?string $lastSeenAt): bool
    {
        if (! $lastSeenAt) {
            return false;
        }

        return Carbon::parse($lastSeenAt)->greaterThan(now()->subSeconds(15));
    }

    private function broadcastState(): void
    {
        broadcast(new \App\Events\StateUpdated(
            $this->getState(),
            $this->getTeams(),
            $this->isBridgeOnline(data_get(Cache::get(self::HEARTBEAT_KEY), 'received_at'))
        ));
    }
}
