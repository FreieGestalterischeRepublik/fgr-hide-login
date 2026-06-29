# FGR Hide Login

Ein WordPress-Plugin der [Freien Gestalterischen Republik](https://fgr.design).

Ändert die WordPress-Login-URL zu einer eigenen, individuellen URL und blockiert den direkten Zugriff auf `wp-login.php`. Erhöht die Sicherheit durch Security by Obscurity – verhindert automatisierte Brute-Force-Angriffe auf die Standard-Login-URL.

---

## Funktionen

- Login-URL frei wählbar (Standard: `/fgr-login/`)
- Direkter Zugriff auf `wp-login.php` wird geblockt (zeigt Theme-404)
- Nicht eingeloggte Benutzer im `wp-admin` werden weitergeleitet
- Weiterleitungs-URL frei wählbar (Standard: `404`)
- Einstellungen unter **FGR Plugins → Hide Login** im WordPress-Backend

---

## Installation

1. ZIP herunterladen und in WordPress unter **Plugins → Installieren → ZIP hochladen** einspielen
2. Plugin aktivieren
3. Einstellungen unter **FGR Plugins → Hide Login** öffnen
4. Neue Login-URL notieren / als Lesezeichen speichern

---

## Einstellungen

| Option | Standard | Beschreibung |
|--------|----------|--------------|
| Login-URL | `fgr-login` | URL-Slug für die Login-Seite |
| Weiterleitungs-URL | `404` | Ziel bei unerlaubtem Zugriff auf wp-admin |

---

## Mindestanforderungen

- PHP 8.0+
- WordPress 6.0+

---

## Autor

**Freie Gestalterische Republik** · [fgr.design](https://fgr.design)
