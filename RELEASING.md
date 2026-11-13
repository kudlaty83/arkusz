# Utrzymanie wydań produkcyjnych

Wersja 1.0.0 jest punktem odniesienia produkcji. Nie wolno resetować bazy użytkowej podczas wdrożenia ani wykonywać na niej testów zapisujących fikcyjne dane. Kopie i dane uruchomieniowe pozostają poza Git.

## Kontrakt wydania

1. Zmień `app/release.json`: wersję programu zgodnie z SemVer, datę oraz docelową rewizję schematu. Wersja API rośnie, gdy zmienia się jego kontrakt; nie musi odpowiadać numerowi programu. Przepisz wersję obrazu w Compose, etykietę w Containerfile i klucz cache PWA.
2. Każde kolejne wydanie ma własną migrację `app/migrations/0002_*.php`, następnie `0003_*.php` itd. Migracja zwraca numer, wydanie, nazwę i funkcję `up`. Jeżeli struktura nie wymaga DDL, dodaj jawną migrację walidującą bez zmian struktury — nadal odnotowuje przyjęcie nowego wydania przez bazę. Podnieś `schema_version` o 1. Nie edytuj żadnego już wydanego pliku migracji.
3. Utrzymuj kompletny `baza.sql` dla nowych instalacji i odpowiadającą mu ścieżkę aktualizacji istniejącej bazy. Migracje nowych wydań muszą tolerować elementy już obecne w kompletnym schemacie świeżej instalacji (np. dodawanie przez `IF NOT EXISTS`). Zmiany danych wykonuj tak, by nie dublować rekordów.
4. Najpierw zmiany rozszerzające, zgodne z zachowanymi danymi. Usunięcia kolumn lub konwersje wymagają wyraźnego planu migracji, weryfikacji danych i opisu w CHANGELOG. Nie dodawaj migracji typu DROP DATABASE ani procedury czyszczenia produkcji.
5. W CHANGELOG podaj wersje programu, API, schematu i obrazów, wymagania aktualizacji oraz plan odzyskania poprzedniego stanu. Każdą zmianę wersji MariaDB testuj oddzielnie od migracji aplikacji.
6. Wymagane testy: instalacja pustej bazy, aktualizacja kopii istniejącego wydania z realnymi relacjami, powtórny start bez kolejnego wykonania migracji, zgodność sum, blokada wersji starszej od bazy oraz odtwarzanie kopii. Dane, identyfikatory, konta, hasła i relacje muszą przetrwać.
7. Dopiero po testach: commit i lokalny tag wydania, np. `v1.0.0`. Publikuj sprawdzony stan źródeł odpowiadający temu wydaniu.

## Wykonywanie migracji

Serwer przy starcie zakłada blokadę inicjalizacji, a silnik migracji dodatkowo blokadę schematu w MariaDB. `schema_migrations` zapisuje numer, wydanie, nazwę, SHA-256, status `running` / `applied` / `failed` i daty. Wcześniejsze rekordy muszą tworzyć ciągłą historię i zgadzać się z niezmienionymi plikami wydania.

Nowa migracja najpierw zapisuje `running`, następnie wykonuje `up`, a dopiero na końcu `applied`. MariaDB wykonuje niektóre DDL z niejawnym zatwierdzeniem — nie zakładamy atomowego cofnięcia całej migracji. Błąd zapisuje `failed`; przerwanie procesu pozostawia `running`. Oba przypadki blokują kolejne automatyczne próby. Brak pliku, zmieniona suma lub schemat z nowszej aplikacji blokują start i healthcheck.

Nie usuwaj wiersza ani nie przepisuj sumy, aby „naprawić” start. Zatrzymaj WWW, zachowaj logi, ustal faktyczny stan struktury i zastosuj udokumentowaną naprawę na kopii lub odtwórz zweryfikowaną kopię sprzed aktualizacji. Nie uruchamiaj starego obrazu na nowszym schemacie.

## Wdrożenie istniejącej instalacji

Przed zmianą źródeł wykonaj zrzut bazy oraz archiwum `config`, zapisz numery używanych wersji i sprawdź możliwość odtworzenia. Zatrzymaj WWW przed uruchomieniem nowego `setup`, ponieważ odświeża on pliki aplikacji na bind mouncie. Zachowaj ten sam `APP_DATA_DIR` i projekt Compose. Procedura użytkownika jest w `install.md`.

Przy braku publicznej domeny aplikacja pozostaje na localhost. Oznaczenie wydania jako produkcyjnego nie oznacza przeprowadzenia zewnętrznego audytu bezpieczeństwa ani przygotowania konfiguracji publicznego hostingu.
