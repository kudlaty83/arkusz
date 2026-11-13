# Testy deweloperskie

Od wydania 1.0.0 instalacja użytkowa jest produkcyjna. Nie uruchamiaj na niej testów — zapisują dane lub celowo symulują błędy migracji. Używaj oddzielnego projektu Compose, APP_DATA_DIR i portu. Testy API i przeglądarkowe wymagają jawnego `ARKUSZ_ALLOW_TEST_DATA=1`; `ARKUSZ_TEST_URL` i `ARKUSZ_TEST_PASSWORD` pozwalają wskazać konto osobnej instalacji. Nie przekazuj hasła jako argumentu procesu ani nie zapisuj go w kodzie.

`smoke.py`, `dictionaries.py`, `orders.py` i `themes.py` sprawdzają lokalną aplikację na porcie 8080; wymagają `.env` z `ADMIN_PASSWORD` pasującym do konta administratora. Przy instalacji Compose użyj odczytanego hasła z `app_data/config/admin-password`, nie zakładaj wspólnego hasła domyślnego. `orders.py` wykonuje również bazowy przebieg `smoke.py`. `browser.py` wymaga zastanego modułu Python `websockets`, Chrome z CDP na porcie 9223 i lokalnego `.env`. Te narzędzia nie są zależnościami uruchomieniowymi aplikacji.

Test nowej instalacji Compose (przykłady dla powłoki Bash):

```sh
APP_DATA_DIR=/tmp/arkusz-bind-test APP_PORT=18080 docker compose -p arkusz-compose-test up -d --build --wait
APP_DATA_DIR=/tmp/arkusz-bind-test APP_PORT=18080 python3 tests/compose.py
```

Z Podmanem oraz dostępnym klientem Compose i gniazdem API:

```sh
APP_DATA_DIR=/tmp/arkusz-bind-test APP_PORT=18080 podman compose -p arkusz-compose-test up -d --build --wait
APP_DATA_DIR=/tmp/arkusz-bind-test APP_PORT=18080 python3 tests/compose.py --engine 'podman compose'
```

Test wymaga już uruchomionego stosu. Sprawdza świeżą bazę, generowanie haseł, logowanie, zapis danych oraz trwałość po `down` i `up`, a także kopie zapasowe. Odtwarza kontenery wyłącznie wskazanego projektu testowego; nie usuwa jego katalogów danych. Sekrety przechowuje w pamięci, a kopie testowe w prywatnym `/tmp/arkusz-compose-backup`, nie w repozytorium. Dla niestandardowego projektu i portu podaj `--project` oraz `--url` i ustaw takie same `APP_PORT` oraz `APP_DATA_DIR` w środowisku. Każdy projekt testowy musi używać osobnego, bezwzględnego katalogu danych.

Wydanie 1.0: `tests/compose.py` sprawdza również wersje programu, API, schematu i silników oraz brak danych demonstracyjnych w domyślnej nowej instalacji. `tests/migrations.py` uruchamiaj wyłącznie na tym samym osobnym stosie, podając jego projekt i adres; celowo symuluje błędy DDL i cofnięcie programu:

```sh
APP_DATA_DIR=/tmp/arkusz-bind-test APP_PORT=18080 python3 tests/migrations.py --project arkusz-compose-test
```

Przykład jawnego uruchomienia testu API na odseparowanej instalacji (hasło przekaż lokalnie przez zmienną `ARKUSZ_TEST_PASSWORD`):

```sh
ARKUSZ_ALLOW_TEST_DATA=1 ARKUSZ_TEST_URL=http://127.0.0.1:18080 python3 tests/smoke.py
```

Pełne testy przeglądarkowe zakładają demonstracyjny produkt z etapami w izolowanej kopii bazy; nie uruchamiaj ich na nowej pustej produkcji. Świeża produkcja tworzy jedynie administratora i słowniki.
