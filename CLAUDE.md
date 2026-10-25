# Projekt: Fußpflege-Website (af-fu-)

Statische One-Pager-Website: `fusspflege_website_one_pager_starter_template.html`.

## Log-Watcher – Übergabe an Claude Code

Ein geplanter Routine-Lauf (Cloud) überwacht eingehende Logs und analysiert sie.
Ergebnisse, die nicht automatisch gefixt wurden, stehen in **`LOG_FINDINGS.md`**.

**Beim Start einer Sitzung in diesem Repo:**
1. `LOG_FINDINGS.md` lesen.
2. Alle Einträge mit Status `OFFEN` abarbeiten (Ursache prüfen, fixen, testen).
3. Erledigte Einträge auf `ERLEDIGT` setzen, mit Commit-Hash.
4. Commit + Push.

Ablauf und Regeln des Watchers: `.claude/log-watcher.md`.
