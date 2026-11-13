# Historia wydań

## Niewydane

- Zmieniono licencję własnych źródeł projektu na AGPL-3.0-only; dodano raport zgodności zależności.

## 1.0.0 — 2026-11-13

Pierwsze wydanie produkcyjne. Program: 1.0.0; API: 1.0; schemat bazy: rewizja 1.

- Obsługa zamówień, produktów, części, parametrów druku, uszlachetnień i opisów.
- Role pracowników, zadania i zależności produkcyjne, raportowanie, edycja oraz słowniki.
- Pięć motywów zapisywanych na kontach i instalacja PWA.
- Compose z bezwzględnymi bind mountami w `app_data`, lokalnymi hasłami i prywatnymi sesjami.
- PHP 8.4.27 z Apache; MariaDB 13.0.2 Stable (GA), bez ruchomego tagu latest.
- Wersje programu, API, schematu oraz faktycznych silników w interfejsie i autoryzowanym API.
- Rejestr migracji z sumami kontrolnymi, blokadą współbieżności oraz wykrywaniem przerwanych migracji i próby cofnięcia wersji.
- Migracja 0001 przyjmuje istniejącą bazę przedprodukcyjną bez kasowania danych. Nowa instalacja nie tworzy danych demonstracyjnych, chyba że jawnie włączono je dla testów.

Przed aktualizacją: kopia logiczna bazy i katalogu konfiguracji. Szczegóły w `install.md`. Aktualizacje wymagają migracji i zachowania danych.
