# Log-Watcher

Ein geplanter Claude-Code-Lauf (Routine) prüft dieses Verzeichnis regelmäßig.

## Logs abliefern

Neue Logdateien (Fehlermeldungen, Konsolen-Ausgaben, Stacktraces, Screenshots als Text …)
einfach in **`logs/inbox/`** ablegen und pushen – auf `main` oder auf
`claude/zen-einstein-1udqj4` (z. B. per GitHub „Add files via upload“).
Dateiname am besten mit Datum + Projekt, z. B. `2026-10-24_dienstplan-app.log`.

## Was der Watcher bei jedem Lauf tut

1. Neue Dateien in `logs/inbox/` (auf `origin/main` und auf dem Watcher-Branch) suchen.
2. Jede Datei analysieren: Fehler finden, betroffenes Projekt/Datei im Repo zuordnen, Ursache bestimmen.
3. **Kleiner, eindeutiger Fehler** → direkt fixen, prüfen, auf `claude/zen-einstein-1udqj4` committen und pushen.
4. **Unklarer oder größerer Fehler** → Eintrag in [`FIXES_TODO.md`](FIXES_TODO.md) mit Analyse und
   Lösungsvorschlag, damit eine lokale Claude-Code-Sitzung das übernehmen kann
   („Lies logs/FIXES_TODO.md und arbeite die offenen Punkte ab“).
5. Verarbeitete Logs nach `logs/processed/` verschieben (damit nichts doppelt gemeldet wird).
6. Push-Benachrichtigung schicken – **nur** wenn neue Logs mit Fehlern da waren.
   Keine neuen Logs → kein Ping.
