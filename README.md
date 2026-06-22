# Eventavoa

Webshop-Projekt für Eventtechnik (Webentwicklungsprojekt, SS 2026). Frontend und Backend tauschen ausschließlich JSON über jQuery `$.ajax` aus.

## Installation

1. Repository nach `C:\xampp\htdocs\Eventavoa` legen.
2. Apache und MySQL über XAMPP starten.
3. `database/eventavoa.sql` in phpMyAdmin importieren. Die Datenbank `eventavoa` wird dabei automatisch erstellt.
4. `http://localhost/eventavoa/` öffnen.

Die Datenbankverbindung befindet sich in `backend/config/dbaccess.php` und verwendet standardmäßig die lokale XAMPP-Datenbank `eventavoa` mit Benutzer `root` ohne Passwort.

## Struktur

- `frontend/`: HTML, CSS, JavaScript und Produktbilder
- `backend/config/`: Datenbank- und gemeinsame API-Basis
- `backend/logic/`: JSON-Endpunkte für Produkte, Login, Warenkorb, Bestellungen und Administration
- `backend/models/`: Domänenmodelle
- `database/`: vollständiger, direkt importierbarer Datenbank-Dump

Der Administrationsbereich liegt unter `frontend/sites/admin.html`. Produktbilder dürfen JPG, PNG oder WebP sein und maximal 5 MB groß sein.
