# Historia wydań

**Arkusz jest aplikacją testową i prototypową, powstałą w większości z użyciem generatywnej AI. Nie jest kompletnym programem dla drukarni. Projekt stanowi zaproszenie do kontaktu, wymiany pomysłów i samodzielnego dalszego rozwoju; numer 1.0.0 nie oznacza gotowości produkcyjnej.**

## 1.1.0 — 2026-11-13

Wydanie testowe. Program: **1.1.0**; API: **1.1**; schemat: **rewizja 2**. Obrazy: PHP `8.4-apache`, MariaDB `13`.

- Ostrzeżenie w edytorze, serwerowy podgląd skutków i osobne potwierdzenie zapisu zamówienia wraz z planem. Raporty i zmiany zadań po podglądzie unieważniają zatwierdzenie.
- Proporcjonalne przeliczenie ilości zadań, przesuwanie terminów i ponowne otwarcie zakończonych zadań z niedoborem.
- Nowe rewizje planu po zmianie technologii; raporty wcześniejszych rewizji zostają w historii i nie zwiększają wykonania nowej specyfikacji. Wstrzymanie dotkniętych etapów i ich następców; w razie potrzeby powrót zamówienia do produkcji.
- Usuwanie produktów, części i cech przez archiwizację; anulowanie niezakończonych zadań i zależnych etapów. Zmiana rodzaju cechy z zachowaniem poprzedniej specyfikacji. Nowe elementy nadal wymagają ręcznego doboru operacji i wykonawców.
- Audyt zaakceptowanych zmian i zachowanie migawki sprzed edycji. Numer rewizji przy zadaniach i raportach.
- Migracja `0002_order_plan.php`: flagi archiwizacji, rewizje zadań i raportów, zdarzenia planu oraz tabela audytu. Zachowuje rekordy i przypisuje dotychczasowe wykonanie do rewizji 1. Migracja bazowa pozostaje niezmieniona.
- API `POST preview_order_update` zwraca listę zmian i token ważny 15 minut. `POST update_order` wymaga `confirmation_token` z podglądu tej samej treści i aktualnego planu. Integracje korzystające z zapisu muszą obsłużyć ten krok.

Aktualizacja z 1.0: wykonaj kopię logiczną bazy i katalogu konfiguracji, zatrzymaj WWW i uruchom Compose według `install.md`. W razie niepowodzenia zatrzymaj usługi, zachowaj logi i odtwórz zweryfikowaną kopię bazy oraz konfiguracji wraz ze źródłami 1.0; nie uruchamiaj starej aplikacji na schemacie 2.

### Pozostałe zmiany od 1.0

- Obrazy kontenerów śledzą teraz PHP 8.4.x (`php:8.4-apache`) i MariaDB 13.x (`mariadb:13`), zamiast konkretnych wersji poprawkowych. Zaktualizowano testy wersji i instrukcję pobierania obrazów.

- Doprecyzowano testowy charakter projektu, udział AI i przeznaczenie do dalszego rozwoju.
- Uzupełniono opis instalacji PWA na telefonach z Androidem i iPhone’ach.

- Zmieniono licencję własnych źródeł projektu na AGPL-3.0-only; dodano raport zgodności zależności.

## 1.0.0 — 2026-11-13

Pierwsze wersjonowane wydanie testowego prototypu. Program: 1.0.0; API: 1.0; schemat bazy: rewizja 1.

- Obsługa zamówień, produktów, części, parametrów druku, uszlachetnień i opisów.
- Role pracowników, zadania i zależności produkcyjne, raportowanie, edycja oraz słowniki.
- Pięć motywów zapisywanych na kontach i instalacja PWA.
- Compose z bezwzględnymi bind mountami w `app_data`, lokalnymi hasłami i prywatnymi sesjami.
- PHP 8.4.27 z Apache; MariaDB 13.0.2 Stable (GA), bez ruchomego tagu latest.
- Wersje programu, API, schematu oraz faktycznych silników w interfejsie i autoryzowanym API.
- Rejestr migracji z sumami kontrolnymi, blokadą współbieżności oraz wykrywaniem przerwanych migracji i próby cofnięcia wersji.
- Migracja 0001 przyjmuje istniejącą bazę z wcześniejszej wersji bez kasowania danych. Nowa instalacja nie tworzy danych demonstracyjnych, chyba że jawnie włączono je dla testów.

Przed aktualizacją: kopia logiczna bazy i katalogu konfiguracji. Szczegóły w `install.md`. Aktualizacje wymagają migracji i zachowania danych.
