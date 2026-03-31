using System.IO.Ports;
using System.Net.Http.Json;
using System.Net.WebSockets;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using System.Threading.Channels;

namespace BridgeLab;

internal sealed class Program
{
    private static readonly string DebugLogPath = Path.Combine(AppContext.BaseDirectory, "bridge-debug.log");

    /// <summary>Quando > DateTime.Now, le press vengono trattate come anticipate (arancione, non inviate al server).</summary>
    private static DateTime _countdownUntil = DateTime.MinValue;

    private static async Task Main(string[] args)
    {
        var config = BridgeConfig.Load();

        Console.WriteLine("BridgeLab avviato");
        Console.WriteLine($"  Laravel  : {config.BaseUrl}");
        Console.WriteLine($"  Reverb   : ws://{config.ReverbHost}:{config.ReverbPort}");
        Console.WriteLine($"  Arduino  : {(config.ArduinoEnabled ? $"{config.ArduinoPort} @ {config.ArduinoBaud}" : "disabilitato")}");
        Console.WriteLine($"  DMX base : {config.DmxBaseAddress}  lamps: {config.DmxLampCount}");
        Console.WriteLine($"  Porte    : {DescribePorts()}");
        Console.WriteLine();

        // ── DMX init ─────────────────────────────────────────────────────
        var dmxOk = false;
        if (config.DmxEnabled)
        {
            dmxOk = DmxController.Initialize();
            Console.WriteLine(dmxOk
                ? $"[{Now()}] DMX ok — self-test lamp 1 rosso per 2s"
                : $"[{Now()}] DMX non disponibile (FTDI non trovato o in uso)");

            if (dmxOk)
            {
                DmxController.SetRed(config.DmxBaseAddress, 1, config.DmxLampCount);
                await Task.Delay(2000);
                DmxController.AllOff(config.DmxBaseAddress, config.DmxLampCount);
                Console.WriteLine($"[{Now()}] DMX self-test ok");
            }
        }

        using var http = new HttpClient
        {
            BaseAddress = new Uri(config.BaseUrl),
            Timeout     = TimeSpan.FromSeconds(8),
        };

        using var cts = new CancellationTokenSource();
        Console.CancelKeyPress += (_, e) => { e.Cancel = true; cts.Cancel(); };

        await Task.WhenAll(
            IgnoreCancellationAsync(RunHeartbeatAsync(http, config, dmxOk, cts.Token)),
            IgnoreCancellationAsync(RunArduinoLoopAsync(http, config, dmxOk, cts.Token)),
            IgnoreCancellationAsync(RunStateSyncLoopAsync(http, config, dmxOk, cts.Token)),
            IgnoreCancellationAsync(RunCommandWebSocketAsync(http, config, dmxOk, cts.Token))
        );

        if (dmxOk) DmxController.AllOff(config.DmxBaseAddress, config.DmxLampCount);
        Console.WriteLine("BridgeLab fermato.");
    }

    // ── Tiene vivo il bridge verso Laravel
    private static async Task RunHeartbeatAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        Program.Trace("RunHeartbeatAsync started");
        using var timer = new PeriodicTimer(TimeSpan.FromSeconds(5));
        while (await timer.WaitForNextTickAsync(ct))
        {
            try
            {
                var payload = new { bridge_name = cfg.BridgeName, version = "2.0.0",
                    serial  = new { port = cfg.ArduinoPort, enabled = cfg.ArduinoEnabled },
                    dmx     = new { enabled = cfg.DmxEnabled, ok = dmxOk, base_addr = cfg.DmxBaseAddress } };
                var res = await http.PostAsJsonAsync("/bridge/heartbeat", payload, ct);
                Console.WriteLine($"[{Now()}] heartbeat {(res.IsSuccessStatusCode ? "ok" : $"ko {(int)res.StatusCode}")}");
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                Console.WriteLine($"[{Now()}] heartbeat ko: {ex.Message}");
            }
        }
    }

    // ── Legge continuamente input da Arduino
    private static async Task RunArduinoLoopAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        Program.Trace("LOOP ATTIVO");
        Program.Trace("RunArduinoLoopAsync started");
        await Task.Yield();
        if (!cfg.ArduinoEnabled) { await Task.Delay(Timeout.Infinite, ct); return; }

        while (!ct.IsCancellationRequested)
        {
            try
            {
                using var port = new SerialPort(cfg.ArduinoPort, cfg.ArduinoBaud)
                    { ReadTimeout = 500, NewLine = "\n", DtrEnable = true };

                port.Open();
                Console.WriteLine($"[{Now()}] Arduino connesso su {cfg.ArduinoPort}");

                while (!ct.IsCancellationRequested)
                {
                    try
                    {
                        var line  = port.ReadLine().Trim();
                        if (string.IsNullOrWhiteSpace(line)) continue;
                        Console.WriteLine($"[{Now()}] rx: {line}");

                        var press = TryParsePress(line);
                        if (press is null || !press.Pressed) continue;

                        // Press anticipata durante il countdown → arancione, non inviare al server
                        var isEarly = DateTime.Now < _countdownUntil;
                        Program.Trace($"PRESS ch={press.Channel} now={DateTime.Now:HH:mm:ss.fff} until={_countdownUntil:HH:mm:ss.fff} early={isEarly}");

                        var earlyPress = await SendEventAsync(http, press.Channel, isEarly, ct);

                        if (dmxOk && DmxController.IsOpen)
                        {
                            if (earlyPress)
                            {
                                DmxController.SetLampEarlyPress(cfg.DmxBaseAddress, press.Channel);
                            }
                            else
                            {
                                DmxController.SetLampBooked(cfg.DmxBaseAddress, press.Channel);
                            }
                        }

                        if (isEarly)
                        {
                            Console.WriteLine($"[{Now()}] PRESS ANTICIPATA ch{press.Channel} → arancione");
                            Program.Trace($"Early press ch{press.Channel} during countdown");

                            if (dmxOk)
                                DmxController.SetLampEarlyPress(cfg.DmxBaseAddress, press.Channel);
                        }

                        // 👇 gestione DMX coerente col server
                        if (dmxOk && DmxController.IsOpen)
                        {
                            if (isEarly || earlyPress)
                            {
                                DmxController.SetLampEarlyPress(cfg.DmxBaseAddress, press.Channel);
                            }
                            else
                            {
                                DmxController.SetLampBooked(cfg.DmxBaseAddress, press.Channel);
                            }
                        }
                    }
                    catch (TimeoutException) { }
                }
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                Console.WriteLine($"[{Now()}] Arduino ko: {ex.Message} | porte: {DescribePorts()}");
                await Task.Delay(1500, ct);
            }
        }
    }

    // ── Sincronizza stato con server ogni 1s
    private static async Task RunStateSyncLoopAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        Program.Trace("RunStateSyncLoopAsync started");
        using var timer = new PeriodicTimer(TimeSpan.FromSeconds(1));
        while (await timer.WaitForNextTickAsync(ct))
        {
            await SyncStateSnapshotAsync(http, cfg, dmxOk, ct);
        }
    }

    // ── Ascolta comandi realtime dal server (Reverb/Pusher)
    private static async Task RunCommandWebSocketAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        Program.Trace("RunCommandWebSocketAsync started");
        var wsUri = new Uri($"ws://{cfg.ReverbHost}:{cfg.ReverbPort}/app/{cfg.ReverbAppKey}?protocol=7&client=csharp&version=1.0");

        while (!ct.IsCancellationRequested)
        {
            using var ws = new ClientWebSocket();
            try
            {
                await ws.ConnectAsync(wsUri, ct);
                Console.WriteLine($"[{Now()}] WebSocket connesso");
                Program.Trace($"WebSocket connected uri={wsUri}");

                // Sottoscrivi il canale comandi
                await WsSendAsync(ws, JsonSerializer.Serialize(new
                {
                    @event = "pusher:subscribe",
                    data   = new { channel = "bridge-commands" }
                }), ct);

                var ms  = new MemoryStream();
                var buf = new byte[8192];

                while (ws.State == WebSocketState.Open && !ct.IsCancellationRequested)
                {
                    ms.SetLength(0);
                    WebSocketReceiveResult result;
                    do
                    {
                        result = await ws.ReceiveAsync(buf, ct);
                        if (result.MessageType == WebSocketMessageType.Close)
                            goto reconnect;
                        ms.Write(buf, 0, result.Count);
                    } while (!result.EndOfMessage);

                    var json = Encoding.UTF8.GetString(ms.ToArray());
                    await HandleWsMessageAsync(ws, json, http, cfg, dmxOk, ct);
                }
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                Console.WriteLine($"[{Now()}] WebSocket ko: {ex.Message} — riconnessione in 2s");
                Program.Trace($"WebSocket error: {ex}");
                await Task.Delay(2000, ct);
            }
            reconnect:;
        }
    }

    // ── Interpreta messaggi WebSocket
    private static async Task HandleWsMessageAsync(ClientWebSocket ws, string json, HttpClient http,
        BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (!root.TryGetProperty("event", out var evtProp)) return;
            var evt = evtProp.GetString();

            switch (evt)
            {
                case "pusher:connection_established":
                    Console.WriteLine($"[{Now()}] WebSocket: connessione stabilita");
                    Program.Trace("WebSocket connection established");
                    break;

                case "pusher_internal:subscription_succeeded":
                case "pusher:subscription_succeeded":
                    Console.WriteLine($"[{Now()}] WebSocket: subscription ok");
                    Program.Trace("WebSocket subscription succeeded");
                    await SyncStateSnapshotAsync(http, cfg, dmxOk, ct);
                    break;

                case "pusher:ping":
                    await WsSendAsync(ws, """{"event":"pusher:pong","data":{}}""", ct);
                    break;

                case "command.issued":
                    if (!root.TryGetProperty("data", out var dataProp)) return;
                    // In Pusher il campo data è una stringa JSON encodata
                    var dataStr = dataProp.ValueKind == JsonValueKind.String
                        ? dataProp.GetString()!
                        : dataProp.GetRawText();
                    using (var doc2 = JsonDocument.Parse(dataStr))
                    {
                        var cmdEl = doc2.RootElement.GetProperty("command");
                        var type  = cmdEl.GetProperty("type").GetString();
                        Console.WriteLine($"[{Now()}] ws cmd: {type}");
                        if (type == "open_buzzer")
                        {
                            if (cmdEl.TryGetProperty("opened_at", out var openedAtEl)
                                && openedAtEl.ValueKind == JsonValueKind.String
                                && DateTime.TryParse(openedAtEl.GetString(), out var openedAt))
                            {
                                _countdownUntil = DateTime.Now.AddSeconds(3);
                            }
                            else
                            {
                                _countdownUntil = DateTime.Now.AddSeconds(3);
                            }

                            Console.WriteLine($"[{Now()}] Countdown: 3s");
                            Program.Trace($"Countdown until {_countdownUntil:HH:mm:ss.fff}");
                        }
                        if (dmxOk) ExecuteDmxCommand(type, cmdEl, cfg);
                    }
                    break;
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[{Now()}] ws parse ko: {ex.Message}");
            Program.Trace($"WebSocket parse error: {ex}");
        }
    }

    // ── Esegue comandi DMX
    private static void ExecuteDmxCommand(string? type, JsonElement cmd, BridgeConfig cfg)
    {
        Program.Trace($"ExecuteDmxCommand type={type ?? "null"}");
        switch (type)
        {
            case "all_off":
                DmxController.AllOff(cfg.DmxBaseAddress, cfg.DmxLampCount);
                Console.WriteLine($"[{Now()}] DMX all off");
                break;

            case "open_buzzer":
                DmxController.StartLoop(cfg.DmxBaseAddress, cfg.DmxLampCount);
                Console.WriteLine($"[{Now()}] DMX loop avviato");
                break;

            case "buzz_channel":
                if (cmd.TryGetProperty("channel", out var chEl) && chEl.TryGetInt32(out var ch))
                {
                    DmxController.SetLampBooked(cfg.DmxBaseAddress, ch);
                    Console.WriteLine($"[{Now()}] DMX lamp {ch} → bianco");
                }
                break;

            case "correct_channel":
                if (cmd.TryGetProperty("channel", out var chOkEl) && chOkEl.TryGetInt32(out var chOk))
                {
                    DmxController.SetLampColor(cfg.DmxBaseAddress, cfg.DmxLampCount, chOk, 0, 255, 0);
                    Console.WriteLine($"[{Now()}] DMX lamp {chOk} → verde");
                }
                break;

            case "wrong_channel":
                if (cmd.TryGetProperty("channel", out var chKoEl) && chKoEl.TryGetInt32(out var chKo))
                {
                    DmxController.SetLampColor(cfg.DmxBaseAddress, cfg.DmxLampCount, chKo, 255, 0, 0);
                    Console.WriteLine($"[{Now()}] DMX lamp {chKo} → rosso");
                }
                break;
        }
    }

    private static async Task SyncStateAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        try
        {
            var data  = await http.GetFromJsonAsync<JsonElement>("/state", ct);
            var phase = data.GetProperty("state").GetProperty("phase").GetString();
            Console.WriteLine($"[{Now()}] sync stato: phase={phase}");
            if (!dmxOk) return;
            switch (phase)
            {
                case "open":
                    DmxController.StartLoop(cfg.DmxBaseAddress, cfg.DmxLampCount);
                    Console.WriteLine($"[{Now()}] sync: loop avviato");
                    break;
                case "locked":
                    var winner = data.GetProperty("state").GetProperty("winner");
                    if (winner.ValueKind != JsonValueKind.Null &&
                        winner.TryGetProperty("channel", out var chEl) &&
                        chEl.TryGetInt32(out var ch))
                    {
                        DmxController.SetLampBooked(cfg.DmxBaseAddress, ch);
                        Console.WriteLine($"[{Now()}] sync: lamp {ch} → booked");
                    }
                    break;
                default:
                    DmxController.AllOff(cfg.DmxBaseAddress, cfg.DmxLampCount);
                    break;
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[{Now()}] sync ko: {ex.Message}");
            Program.Trace($"SyncStateSnapshot error: {ex}");
        }
    }

    // ── Versione migliorata della sync
    private static async Task SyncStateSnapshotAsync(HttpClient http, BridgeConfig cfg, bool dmxOk, CancellationToken ct)
    {
        try
        {
            var data  = await http.GetFromJsonAsync<JsonElement>("/state", ct);
            var phase = data.GetProperty("state").GetProperty("phase").GetString();
            var winner = data.GetProperty("state").GetProperty("winner");

            int? winnerChannel = null;
            if (winner.ValueKind != JsonValueKind.Null &&
                winner.TryGetProperty("channel", out var winnerChEl) &&
                winnerChEl.TryGetInt32(out var winnerCh))
            {
                winnerChannel = winnerCh;
            }

            if (phase is null) return;
            if (!DmxController.TryBeginStateApply(phase, winnerChannel)) return;

            Program.Trace($"SyncStateSnapshot apply phase={phase} winner={winnerChannel?.ToString() ?? "-"}");
            Console.WriteLine($"[{Now()}] sync stato: phase={phase}{(winnerChannel is int chInfo ? $" winner={chInfo}" : "")}");
            if (!dmxOk) return;

            switch (phase)
            {
                case "open":
                    DmxController.StartLoop(cfg.DmxBaseAddress, cfg.DmxLampCount);
                    Console.WriteLine($"[{Now()}] sync: loop avviato");
                    break;
                case "locked":
                    if (winnerChannel is int ch)
                    {
                        DmxController.SetLampBooked(cfg.DmxBaseAddress, ch);
                        Console.WriteLine($"[{Now()}] sync: lamp {ch} -> booked");
                    }
                    break;
                default:
                    Console.WriteLine($"[{Now()}] sync: idle, nessuna azione DMX");
                    break;
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[{Now()}] sync ko: {ex.Message}");
        }
    }

    // ── Invia messaggi WebSocket
    private static async Task WsSendAsync(ClientWebSocket ws, string message, CancellationToken ct)
    {
        var bytes = Encoding.UTF8.GetBytes(message);
        await ws.SendAsync(bytes, WebSocketMessageType.Text, true, ct);
    }

    // ── Invia pressione al server
    /// <summary>Invia una press al server. Ritorna true se il server risponde early=true (press anticipata).</summary>
    private static async Task<bool> SendEventAsync(HttpClient http, int channel, bool early, CancellationToken ct)
    {
        try
        {
            var res = await http.PostAsJsonAsync("/bridge/event", new
            {
                channel,
                pressed = true,
                early = early   // 👈 NUOVO
            }, ct);

            Program.Trace($"SEND → ch={channel} early={early}");

            Console.WriteLine($"[{Now()}] evento ch{channel}: {(res.IsSuccessStatusCode ? "ok" : $"ko {(int)res.StatusCode}")}");

            if (res.IsSuccessStatusCode)
            {
                var body = await res.Content.ReadFromJsonAsync<JsonElement>(cancellationToken: ct);

                // opzionale: puoi anche ignorarlo ora
                if (body.TryGetProperty("early", out var earlyProp) && earlyProp.GetBoolean())
                    return true;
            }
        }
        catch (Exception ex) when (ex is not OperationCanceledException)
        {
            Console.WriteLine($"[{Now()}] evento ko: {ex.Message}");
        }

        return false;
    }

    // ── Interpreta input Arduino tt
    private static ArduinoPress? TryParsePress(string line)
    {
        var parts = line.Split('|', ':', ' ');
        if (parts.Length >= 3)
        {
            var tag = parts[0].Trim().ToUpperInvariant();
            if ((tag == "BTN" || tag == "PRESS") &&
                int.TryParse(parts[1].Trim(), out var ch) && ch > 0 &&
                int.TryParse(parts[2].Trim(), out var val))
                return new ArduinoPress(ch, val == 1);
        }
        if (int.TryParse(line, out var direct) && direct > 0)
            return new ArduinoPress(direct, true);
        return null;
    }

    private static string DescribePorts()
    {
        var ports = SerialPort.GetPortNames().OrderBy(p => p).ToArray();
        return ports.Length == 0 ? "nessuna" : string.Join(", ", ports);
    }

    private static string Now() => DateTime.Now.ToString("HH:mm:ss");

    internal static void Trace(string message)
    {
        var line = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss.fff}] {message}";
        Console.WriteLine(line);

        try
        {
            File.AppendAllText(DebugLogPath, line + Environment.NewLine);
        }
        catch
        {
            // Ignore file logging failures; console output remains the source of truth.
        }
    }

    private static async Task IgnoreCancellationAsync(Task t)
    {
        try { await t; } catch (OperationCanceledException) { }
    }
}

// ── DMX Controller (FTDI OpenDMX) ────────────────────────────────────────────

internal static class DmxController
{
    private static readonly byte[] _buf = new byte[513];
    private static IntPtr _handle = IntPtr.Zero;
    private static bool _ok;
    private static bool _done = true;
    private static Thread? _writerThread;

    // ── Loop animation state ──────────────────────────────────────────────────
    private enum LampMode : byte { Off, Loop, Booked, EarlyPress }
    private static readonly LampMode[] _modes = new LampMode[9]; // 1-based, lamps 1–8
    private static volatile bool _loopRunning;
    private static Thread? _loopThread;
    private static int _loopBase;
    private static int _loopCount;
    private static readonly object _stateLock = new();
    private static string? _appliedPhase;
    private static int? _appliedWinnerChannel;

    /// <summary>True quando il buzzer è aperto (loop in corso). Usato dall'Arduino loop.</summary>
    public static bool IsOpen { get; private set; }

    public static bool TryBeginStateApply(string phase, int? winnerChannel)
    {
        lock (_stateLock)
        {
            var loopNeedsRecovery = !_loopRunning || _loopThread is null || !_loopThread.IsAlive;
            Program.Trace($"TryBeginStateApply phase={phase} winner={winnerChannel?.ToString() ?? "-"} recovery={loopNeedsRecovery} isOpen={IsOpen} loopRunning={_loopRunning} threadAlive={(_loopThread?.IsAlive == true)}");
            if (phase == "open" && loopNeedsRecovery)
            {
                _appliedPhase = phase;
                _appliedWinnerChannel = winnerChannel;
                return true;
            }

            if (_appliedPhase == phase && _appliedWinnerChannel == winnerChannel)
            {
                return false;
            }

            _appliedPhase = phase;
            _appliedWinnerChannel = winnerChannel;
            return true;
        }
    }

    public static bool Initialize()
    {
        try
        {
            _done = true;
            _writerThread?.Join(300);

            _handle = IntPtr.Zero;
            var st = FT_Open(0, ref _handle);

            if (st != FT_STATUS.FT_OK || _handle == IntPtr.Zero)
            {
                Console.WriteLine($"[DMX] FT_Open ko: {st}");
                return false;
            }

            _buf[0] = 0; // DMX start code
            _done = false;
            _ok   = true;

            _writerThread = new Thread(WriterLoop) { IsBackground = true, Name = "DmxWriter" };
            _writerThread.Start();
            return true;
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[DMX] init ko: {ex.Message}");
            return false;
        }
    }

    /// <summary>Avvia l'animazione loop (blu ciclico). Tutte le lampade entrano in modalità Loop.</summary>
    public static void StartLoop(int baseAddress, int lampCount)
    {
        if (!_ok) return;

        // 🔥 NUOVO: se già attivo → NON fare nulla
        if (_loopRunning && _loopThread is not null && _loopThread.IsAlive)
        {
            return;
        }

        Program.Trace($"StartLoop requested base={baseAddress} lamps={lampCount} prevThreadAlive={(_loopThread?.IsAlive == true)}");
        _loopBase  = baseAddress;
        _loopCount = lampCount;
        for (var i = 1; i <= lampCount; i++)
        {
            if (_modes[i] != LampMode.EarlyPress)
            {
                _modes[i] = LampMode.Loop;
            }
        }

        IsOpen = true;
        if (_loopRunning && _loopThread is not null && _loopThread.IsAlive)
        {
            Program.Trace("StartLoop skipped restart because loop is already active");
            return;
        }

        _loopRunning = false;
        _loopThread?.Join(400);
        _loopRunning = true;
        _loopThread = new Thread(LoopAnimation) { IsBackground = true, Name = "DmxLoop" };
        _loopThread.Start();
        Program.Trace($"StartLoop started threadAlive={_loopThread.IsAlive} isOpen={IsOpen} loopRunning={_loopRunning}");
    }

    // Colore assegnato a ciascuna lampada (1-based): R, G, B
    private static readonly (byte R, byte G, byte B)[] _lampColors =
    [
        (0,   0,   0),   // [0] unused
        (0,   0,   255), // lamp 1 → blu
        (0,   255, 0),   // lamp 2 → verde
        (255, 0,   0),   // lamp 3 → rosso
        (255, 255, 0),   // lamp 4 → giallo
        (160, 0,   255), // lamp 5 → viola
        (255, 0,   160), // lamp 6 → rosa
        (0,   255, 255), // lamp 7 → ciano
        (180, 255, 0),   // lamp 8 → lime
    ];

    /// <summary>Prenota una lampada: bianco fisso, tutte le altre spente.</summary>
    public static void SetLampBooked(int baseAddress, int lamp)
    {
        if (!_ok || lamp < 1 || lamp > 8) return;
        if (!IsOpen)
            StopLoop();
        if (_loopCount > 0)
            for (var i = baseAddress; i < baseAddress + _loopCount * 4; i++) _buf[i] = 0;
        for (var i = 1; i < _modes.Length; i++) _modes[i] = LampMode.Off;

        _modes[lamp] = LampMode.Booked;
        var addr = baseAddress + (lamp - 1) * 4;
        _buf[addr]     = 255; // R
        _buf[addr + 1] = 255; // G
        _buf[addr + 2] = 255; // B
        _buf[addr + 3] = 0;
    }

    /// <summary>Accende una singola lampada con colore specificato, le altre spente.</summary>
    public static void SetLampColor(int baseAddress, int lampCount, int lamp, byte r, byte g, byte b)
    {
        if (!_ok || lamp < 1 || lamp > 8) return;
        StopLoop();
        for (var i = baseAddress; i < baseAddress + lampCount * 4; i++) _buf[i] = 0;
        var addr = baseAddress + (lamp - 1) * 4;
        _buf[addr]     = r;
        _buf[addr + 1] = g;
        _buf[addr + 2] = b;
        _buf[addr + 3] = 0;
    }

    /// <summary>Marca una lampada come "premuta in anticipo" — arancione fisso, loop continua per le altre.</summary>
    public static void SetLampEarlyPress(int baseAddress, int lamp)
    {
        if (lamp < 1 || lamp > 8) return;
        _modes[lamp] = LampMode.EarlyPress;
        if (_ok)
        {
            var addr = baseAddress + (lamp - 1) * 4;
            _buf[addr]     = 255; // R
            _buf[addr + 1] = 80;  // G → arancione
            _buf[addr + 2] = 0;   // B
            _buf[addr + 3] = 0;
        }
    }

    /// <summary>Ferma l'animazione loop senza spegnere le lampade.</summary>
    public static void StopLoop()
    {
        Program.Trace($"StopLoop isOpen={IsOpen} loopRunning={_loopRunning}");
        IsOpen = false;
        _loopRunning = false;
    }

    /// <summary>Accende lamp N (1-based) in rosso. base = DMX address del lamp 1.</summary>
    public static void SetLampRed(int baseAddress, int lamp)
    {
        if (!_ok) return;
        var addr = baseAddress + (lamp - 1) * 4;
        _buf[addr]     = 255; // R
        _buf[addr + 1] = 0;   // G
        _buf[addr + 2] = 0;   // B
        _buf[addr + 3] = 0;
    }

    /// <summary>Accende tutti i lamp in rosso (self-test).</summary>
    public static void SetRed(int baseAddress, int fromLamp, int lampCount)
    {
        if (!_ok) return;
        for (var i = fromLamp; i <= lampCount; i++) SetLampRed(baseAddress, i);
    }

    /// <summary>Spegne tutto e ferma l'animazione loop.</summary>
    public static void AllOff(int baseAddress, int lampCount)
    {
        if (!_ok) return;
        Program.Trace($"AllOff base={baseAddress} lamps={lampCount}");
        IsOpen = false;
        StopLoop();
        for (var i = 1; i <= lampCount; i++) _modes[i] = LampMode.Off;
        for (var i = baseAddress; i < baseAddress + lampCount * 4; i++)
            _buf[i] = 0;
    }

    private static void LoopAnimation()
    {
        Program.Trace($"LoopAnimation entered thread={Environment.CurrentManagedThreadId} lamps={_loopCount}");
        var cur = 0;
        while (_loopRunning)
        {
            // Raccoglie le lampade ancora in modalità Loop
            var loopLamps = new List<int>();
            for (var i = 1; i <= _loopCount; i++)
                if (_modes[i] == LampMode.Loop) loopLamps.Add(i);

            if (loopLamps.Count == 0) { Thread.Sleep(100); continue; }

            cur %= loopLamps.Count;
            var active = loopLamps[cur];

            // Aggiorna il buffer DMX
            for (var i = 1; i <= _loopCount; i++)
            {
                var addr = _loopBase + (i - 1) * 4;
                switch (_modes[i])
                {
                    case LampMode.Booked:
                        // Colore fisso del canale
                        var (r, g, b) = i < _lampColors.Length ? _lampColors[i] : ((byte)255, (byte)0, (byte)0);
                        _buf[addr]     = r; _buf[addr + 1] = g; _buf[addr + 2] = b; _buf[addr + 3] = 0;
                        break;
                    case LampMode.EarlyPress:
                        // Arancione fisso — press anticipata durante il countdown
                        _buf[addr]     = 255; _buf[addr + 1] = 80; _buf[addr + 2] = 0; _buf[addr + 3] = 0;
                        break;
                    case LampMode.Loop when i == active:
                        // Blu — lampada attiva nell'animazione loop
                        _buf[addr]     = 0; _buf[addr + 1] = 0; _buf[addr + 2] = 255; _buf[addr + 3] = 0;
                        break;
                    default:
                        // Off
                        _buf[addr]     = 0; _buf[addr + 1] = 0; _buf[addr + 2] = 0; _buf[addr + 3] = 0;
                        break;
                }
            }

            cur++;
            Thread.Sleep(333); // FlashLatency identico a BuzzIT
        }
        Program.Trace($"LoopAnimation exited thread={Environment.CurrentManagedThreadId} isOpen={IsOpen} loopRunning={_loopRunning}");
    }

    private static void WriterLoop()
    {
        while (!_done)
        {
            if (_handle == IntPtr.Zero) { _done = true; break; }

            var st = FT_ResetDevice(_handle);
            if (st != FT_STATUS.FT_OK) { Thread.Sleep(20); continue; }
            st = FT_SetDivisor(_handle, (char)12);
            if (st != FT_STATUS.FT_OK) { Thread.Sleep(20); continue; }
            FT_SetDataCharacteristics(_handle, 8, 2, 0);
            FT_SetFlowControl(_handle, (char)0, 0, 0);
            FT_ClrRts(_handle);
            FT_Purge(_handle, PURGE_TX);
            FT_Purge(_handle, PURGE_RX);

            FT_SetBreakOn(_handle);
            FT_SetBreakOff(_handle);

            var ptr = Marshal.AllocHGlobal(_buf.Length);
            try
            {
                Marshal.Copy(_buf, 0, ptr, _buf.Length);
                uint written = 0;
                FT_Write(_handle, ptr, (uint)_buf.Length, ref written);
            }
            finally { Marshal.FreeHGlobal(ptr); }

            Thread.Sleep(20);
        }
    }

    // ── FTDI P/Invoke ─────────────────────────────────────────────────────────
    private const uint PURGE_RX = 1;
    private const uint PURGE_TX = 2;

    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_Open(uint p, ref IntPtr h);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_Write(IntPtr h, IntPtr buf, uint n, ref uint written);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_SetDataCharacteristics(IntPtr h, byte word, byte stop, byte par);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_SetFlowControl(IntPtr h, char fc, byte xon, byte xoff);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_Purge(IntPtr h, uint mask);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_ClrRts(IntPtr h);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_SetBreakOn(IntPtr h);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_SetBreakOff(IntPtr h);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_ResetDevice(IntPtr h);
    [DllImport("FTD2XX.dll")] static extern FT_STATUS FT_SetDivisor(IntPtr h, char div);

    private enum FT_STATUS
    {
        FT_OK = 0, FT_INVALID_HANDLE, FT_DEVICE_NOT_FOUND, FT_DEVICE_NOT_OPENED,
        FT_IO_ERROR, FT_INSUFFICIENT_RESOURCES, FT_INVALID_PARAMETER, FT_INVALID_BAUD_RATE,
        FT_FAILED_TO_WRITE_DEVICE = 10, FT_OTHER_ERROR = 17
    }
}

// ── Config ───────────────────────────────────────────────────────────────────

internal sealed record BridgeConfig(
    string BaseUrl, string BridgeName,
    bool ArduinoEnabled, string ArduinoPort, int ArduinoBaud,
    bool DmxEnabled, int DmxBaseAddress, int DmxLampCount,
    string ReverbHost, int ReverbPort, string ReverbAppKey)
{
    public static BridgeConfig Load()
    {
        // Legge bridgelab.json dalla stessa cartella dell'exe (o dalla cwd)
        var j = LoadJson();

        string Str(string jsonKey, string envKey, string def)
        {
            var v = Environment.GetEnvironmentVariable(envKey);
            if (!string.IsNullOrEmpty(v)) return v;
            if (j.TryGetProperty(jsonKey, out var jp) && jp.ValueKind == JsonValueKind.String)
                return jp.GetString()!;
            return def;
        }
        bool Bool(string jsonKey, string envKey, bool def)
        {
            var v = Environment.GetEnvironmentVariable(envKey);
            if (!string.IsNullOrEmpty(v)) return !string.Equals(v, "false", StringComparison.OrdinalIgnoreCase);
            if (j.TryGetProperty(jsonKey, out var jp) && jp.ValueKind == JsonValueKind.True  ) return true;
            if (j.TryGetProperty(jsonKey, out var jp2) && jp2.ValueKind == JsonValueKind.False) return false;
            return def;
        }
        int Int(string jsonKey, string envKey, int def)
        {
            var v = Environment.GetEnvironmentVariable(envKey);
            if (int.TryParse(v, out var ev)) return ev;
            if (j.TryGetProperty(jsonKey, out var jp) && jp.TryGetInt32(out var jv)) return jv;
            return def;
        }

        return new BridgeConfig(
            BaseUrl:        Str ("base_url",        "BRIDGE_BASE_URL",        "http://127.0.0.1:8000").TrimEnd('/'),
            BridgeName:     Str ("bridge_name",     "BRIDGE_NAME",            Environment.MachineName),
            ArduinoEnabled: Bool("arduino_enabled", "BRIDGE_ARDUINO_ENABLED", true),
            ArduinoPort:    Str ("arduino_port",    "BRIDGE_ARDUINO_PORT",    "COM5"),
            ArduinoBaud:    Int ("arduino_baud",    "BRIDGE_ARDUINO_BAUD",    9600),
            DmxEnabled:     Bool("dmx_enabled",     "BRIDGE_DMX_ENABLED",     true),
            DmxBaseAddress: Int ("dmx_base",        "BRIDGE_DMX_BASE",        33),
            DmxLampCount:   Int ("dmx_lamps",       "BRIDGE_DMX_LAMPS",       4),
            ReverbHost:     Str ("reverb_host",     "REVERB_HOST",            "127.0.0.1"),
            ReverbPort:     Int ("reverb_port",     "REVERB_PORT",            8080),
            ReverbAppKey:   Str ("reverb_app_key",  "REVERB_APP_KEY",         "my-app-key")
        );
    }

    private static JsonElement LoadJson()
    {
        var paths = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "bridgelab.json"),
            "bridgelab.json",
        };
        foreach (var path in paths)
        {
            if (!File.Exists(path)) continue;
            try
            {
                var doc = JsonDocument.Parse(File.ReadAllText(path));
                Console.WriteLine($"  Config   : {path}");
                return doc.RootElement;
            }
            catch { }
        }
        return JsonDocument.Parse("{}").RootElement;
    }
}

// ── Models ───────────────────────────────────────────────────────────────────

internal sealed record ArduinoPress(int Channel, bool Pressed);
internal sealed record CommandEnvelope(BridgeCommand? Command);
internal sealed record BridgeCommand(string Id, string Type, int? Channel, string? Color, string? QueuedAt);
