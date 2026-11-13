# Instalacja i aktualizacja Arkusza 1.1

Instrukcja dla osoby uruchamiającej własną drukarnię w aplikacji. Nie trzeba instalować PHP, MariaDB, Node.js ani Pythona na komputerze. Opis funkcji jest w [README.md](README.md).

## 1. Przygotuj komputer

Zainstaluj i uruchom **Docker Desktop** (Windows/macOS/Linux) albo **Docker Engine z aktualną wtyczką Docker Compose** na Linuksie. Używaj kontenerów linuksowych i polecenia `docker compose`, nie starego `docker-compose` v1. Pierwsze uruchomienie wymaga internetu do pobrania obrazów.

- [Docker Desktop](https://docs.docker.com/get-started/get-docker/)
- [Docker Engine na Linuksie](https://docs.docker.com/engine/install/)
- [Docker Compose](https://docs.docker.com/compose/install/)

Pobierz źródła wydania z repozytorium projektu (np. **Code → Download ZIP**), rozpakuj i otwórz terminal w katalogu zawierającym `compose.yaml`. Polecenia w dalszej części wykonuj w tym katalogu.

Sprawdzenie dostępności narzędzi:

```sh
docker version
docker compose version
```

## 2. Uruchom jednym poleceniem

Na Windows ustaw najpierw bezwzględny `APP_DATA_DIR` w `.env` (przykład w sekcji „Katalog danych i port”). Na Linuksie/macOS domyślnie wystarczy bieżący katalog projektu.

```sh
docker compose up -d --build --wait
```

Po zakończeniu otwórz **http://localhost:8080**. Pierwsza instalacja może potrwać kilka minut. Kolejne uruchomienia są szybsze.

Na Linuksie/macOS, w katalogu projektu, domyślna lokalizacja danych to bezwzględna ścieżka `$PWD/app_data`. Nie musisz tworzyć `.env`, ręcznie importować SQL ani ustawiać haseł. Na Windows bez zmiennej `PWD` wpisz najpierw `APP_DATA_DIR` w `.env`, zgodnie z sekcją „Katalog danych i port”. Compose:

1. Buduje obraz aplikacji PHP/Apache z lokalnych źródeł.
2. Generuje losowe, oddzielne hasła bazy, administratora bazy i pierwszego administratora aplikacji.
3. Zapisuje hasła w `app_data/config`, przygotowuje pliki aplikacji w `app_data/app` i prywatny katalog sesji.
4. Uruchamia MariaDB i czeka na gotowość bazy.
5. Tworzy schemat, wykonuje dostępne aktualizacje schematu i zakłada słowniki, konto administratora bez tworzenia przykładowych zamówień.
6. Udostępnia stronę dopiero po inicjalizacji i sprawdza jej gotowość.

Usługa `setup` powinna zakończyć się kodem 0 — to jednorazowe przygotowanie konfiguracji, nie serwer wymagający ciągłego działania. Dwie pozostałe usługi, `db` i `web`, pozostają uruchomione. Ponowny start nie zmienia wygenerowanych haseł ani nie dubluje danych startowych.

## 3. Pierwsze logowanie

Login: **`admin`**.

Pierwsze hasło jest zapisane w pliku **`app_data/config/admin-password`** (lub `<APP_DATA_DIR>/config/admin-password`). Możesz odczytać je bezpośrednio z dysku. Przy rootless Podmanie na Linuksie będzie to zwykle:

```sh
cat app_data/config/admin-password
```

Przy Dockerze plik może należeć do administratora systemu — użyj odpowiednich uprawnień albo odczytaj go przez kontener:

```sh
docker compose exec web cat /run/arkusz-secrets/admin-password
```

Skopiuj wynik do pola hasła na stronie. Nie publikuj tego wyniku, plików haseł ani kopii bazy w repozytorium. Hasło jest lokalne dla tej instalacji; repozytorium nie zawiera wspólnego, domyślnego hasła administratora.

W **Zespół** utwórz konta pracowników, wybierz role oraz stanowiska. W **Słowniki** dostosuj papiery, operacje i cechy do własnej drukarni. Utwórz pierwsze własne zamówienie. To testowy prototyp w wersji 1.1.0, powstały w większości z użyciem AI; wymaga samodzielnej oceny i dalszego rozwoju. Dane demonstracyjne są dostępne tylko po jawnym ustawieniu `SEED_DEMO=1` przy tworzeniu pustej bazy testowej; istniejące konta i zamówienia nigdy nie są przez to resetowane.

## Codzienne uruchamianie i zatrzymywanie

Zatrzymanie usług, bez usuwania danych:

```sh
docker compose stop
```

Ponowne uruchomienie:

```sh
docker compose up -d --wait
```

Usunięcie kontenerów i sieci, z zachowaniem danych:

```sh
docker compose down
```

Compose nie deklaruje nazwanych wolumenów. `down` ani `down -v` nie usuwają katalogów bind mountów na dysku. Dane mogą zostać utracone dopiero po usunięciu lub podmienieniu katalogów `app_data` przez administratora. Zachowuj **database i config razem**. Usunięcie samych haseł przy zachowanej bazie spowoduje wygenerowanie innych danych logowania, które nie będą pasować do istniejącej bazy.

Kontenery mają `restart: unless-stopped`. Po restarcie komputera wrócą, jeśli uruchomi się silnik Docker i nie były wcześniej celowo zatrzymane.

## Katalog danych i port

Układ katalogów na dysku:

```text
app_data/
├── database/               # fizyczne pliki MariaDB
├── app/                    # pliki PHP, HTML, CSS, JS i schemat kopiowane z obrazu
├── config/
│   ├── admin-password      # pierwsze hasło konta admin
│   ├── db-password         # hasło konta bazy używanego przez aplikację
│   └── db-root-password    # hasło administratora MariaDB
└── sessions/               # prywatne pliki sesji PHP
```

Wszystkie montowania są typu **bind** z bezwzględnymi ścieżkami. Domyślny katalog jest wyliczany z `PWD`, ale możesz wskazać własny przez `.env`:

```dotenv
APP_DATA_DIR=/srv/arkusz/app_data
APP_PORT=8080
```

Na Docker Desktop / Windows użyj ścieżki z literą dysku i ukośnikami, np.:

```dotenv
APP_DATA_DIR=C:/Arkusz/app_data
APP_PORT=8080
```

Katalog musi być zapisywalny dla silnika kontenerów. Nie używaj ścieżki względnej ani wspólnego katalogu danych dla kilku instalacji. Jeśli jest to lokalizacja w repozytorium inna niż `app_data`, dodaj ją do `.gitignore`, `.dockerignore` i `.containerignore`; domyślny `app_data` jest już wykluczony. Nie ustawiaj wszystkim plikom uprawnień `777` — konfiguracja przygotowuje potrzebne prawa, a sesje mają prywatny katalog.

Dane konfiguracyjne pozostają na dysku między uruchomieniami. Edycja pliku `admin-password` nie resetuje hasła konta, które już istnieje w bazie. Plik nie jest publikowany przez serwer WWW.

`app_data/app` jest kopią plików dostarczanych przez obraz. Serwer montuje ją tylko do odczytu; usługa `setup` odświeża zarządzane pliki przy uruchomieniu nowej wersji. Modyfikacje kodu wprowadzaj w katalogu źródłowym `app/` i przebuduj obraz — ręczne zmiany plików zarządzanych w `app_data/app` zostaną zastąpione. Sesje i konfiguracja nie leżą w katalogu publicznym aplikacji. Obsługa przesyłania plików nie jest jeszcze zaimplementowana.

Przy zajętym porcie wpisz np. `APP_PORT=8081`. Następnie wykonaj:

```sh
docker compose up -d --build --wait
```

Adres będzie wtedy **http://localhost:8081**. Dla wielu instalacji ustaw osobne `APP_DATA_DIR`, porty oraz nazwy projektów (`docker compose -p nazwa ...`). Sama zmiana `-p` nie zmienia wskazanego katalogu danych. Zawsze używaj tego samego `APP_DATA_DIR`; wskazanie pustej lokalizacji oznacza nową instalację. Przenosząc katalog, zatrzymaj wszystkie usługi i przenieś całość z zachowaniem właścicieli i uprawnień.

## Dostęp z innych urządzeń i instalacja na telefonie

Domyślna konfiguracja wystawia aplikację wyłącznie na `127.0.0.1`, a baza nie wystawia portu na komputerze. To instalacja lokalna.

Udostępnienie w sieci wymaga własnej konfiguracji domeny, reverse proxy i HTTPS. Nie wystarczy przekazać innym osobom adresu `localhost`, który zawsze oznacza ich własny komputer. Aplikacja nie dostarcza gotowej konfiguracji publicznego hostingu.

Aplikację można zainstalować jako PWA na komputerze i na telefonie:

- **Android:** otwórz stronę aplikacji w Chrome i użyj opcji instalacji lub dodania do ekranu głównego w menu przeglądarki.
- **iPhone:** otwórz stronę w Safari i w menu udostępniania wybierz dodanie do ekranu początkowego.
- **Komputer:** na localhost Chrome może zaproponować instalację z menu przeglądarki lub przycisku aplikacji.

Dla telefonu udostępnij serwer pod osiągalnym adresem HTTPS. Adres `localhost` na telefonie wskazuje sam telefon, a nie komputer z aplikacją. Domyślna konfiguracja Compose nasłuchuje tylko na komputerze serwera, więc samo uruchomienie kontenerów nie zapewnia dostępu z telefonu. Instalacja PWA nie zastępuje działającego serwera; zamówienia i zapisy wymagają połączenia.

## Kopie zapasowe

Wykonuj je przed aktualizacjami i regularnie podczas pracy. Poniższe przykłady powłoki `sh`/Bash działają na Linuksie, macOS i w WSL. Polecenia instalacji Compose działają również z PowerShell; przekierowania kopii zapasowych najlepiej wykonywać w powłoce wskazanej powyżej.

Utwórz prywatny katalog na kopie:

```sh
umask 077
mkdir -p backups
```

Kopia logiczna bazy:

```sh
docker compose exec -T db sh -c 'exec mariadb-dump -udrukarnia -p"$(cat "$MARIADB_PASSWORD_FILE")" --single-transaction drukarnia' > backups/baza.sql
```

Kopia katalogu konfiguracji z hasłami (odczyt przez kontener pozwala zachować prawa plików):

```sh
docker compose run --rm --no-deps --entrypoint tar setup -C /run/arkusz-secrets -cf - . > backups/config.tar
```

Przenieś kopie do bezpiecznego miejsca poza komputerem aplikacji. Hasła nie są szyfrowane przez tę konfigurację. Pliki bazy i haseł są wykluczone z kontekstu budowania obrazu i z repozytorium Git. Okresowo sprawdzaj odtwarzanie na osobnej instalacji.

### Odtworzenie do nowej instalacji

Nie wykonuj tego na działającej bazie z danymi, które chcesz zachować. Użyj tych samych źródeł oraz **nowego, pustego `APP_DATA_DIR`**, osobnej nazwy projektu i innego portu. Dotychczasowe katalogi pozostaw bez zmian. W szczególności nie wystarczy samo `-p`: bind mounty zależą od `APP_DATA_DIR`.

1. Umieść obie kopie w katalogu `backups` nowej instalacji. Odtwórz hasła **przed pierwszym uruchomieniem MariaDB**:

   ```sh
   docker compose build
   docker compose run --rm --no-deps -T --entrypoint tar setup -C /run/arkusz-secrets -xf - < backups/config.tar
   ```

2. Uruchom wyłącznie bazę, a następnie zaimportuj zrzut:

   ```sh
   docker compose up -d --wait db
   docker compose exec -T db sh -c 'exec mariadb -udrukarnia -p"$(cat "$MARIADB_PASSWORD_FILE")" drukarnia' < backups/baza.sql
   ```

3. Uruchom całość:

   ```sh
   docker compose up -d --build --wait
   ```

Używane będą konta i hasła zapisane w kopii. Po odtworzeniu sprawdź zamówienia, role i możliwość logowania.

## Aktualizacja

Wersja 1.1.0 aktualizuje instalację 1.0 migracją 0002 do schematu 2, zachowując konta, zamówienia, przydziały i raporty. Dotychczasowe zadania i raporty należą do rewizji planu 1. Po aktualizacji edycja wymaga podglądu i osobnego potwierdzenia zmian. Szczegółowe reguły przeliczania opisuje [README.md](README.md#edycja-i-historia).

Zrób kopię bazy i haseł. Pobierz nową wersję źródeł do tego samego katalogu, zachowując cały `app_data` i opcjonalny `.env`. Zatrzymaj serwer WWW na czas aktualizacji schematu:

```sh
docker compose stop web
docker compose up -d --build --wait
```

Przed każdą aktualizacją wykonaj obie kopie zapasowe, sprawdź ich odtwarzanie i przeczytaj CHANGELOG. Migracje są sprawdzane przy każdym starcie; stosowane są wyłącznie brakujące, w ustalonej kolejności. Baza zapisuje ich numery, sumy kontrolne i status. Niezgodność, starszy program lub przerwana migracja blokują start — nie usuwaj rejestru i nie kasuj danych, aby obejść blokadę. MariaDB może zatwierdzać DDL poza transakcją, więc przy nieudanej migracji potrzebna jest kontrolowana naprawa lub odtworzenie kopii, nie automatyczne cofnięcie SQL. Procedura dla opiekuna: RELEASING.md.

Obrazy śledzą gałęzie **`php:8.4-apache`** (PHP 8.4.x) i **`mariadb:13`** (MariaDB 13.x). Nie używamy `latest` ani tagów RC. Są to tagi ruchome: kolejne pobranie może dostarczyć nowszą wersję w wybranej linii, a MariaDB także nowszą podgałąź 13.x. Nie oznacza to przypięcia do LTS. Faktyczne wersje uruchomionych silników pokazuje aplikacja. Źródła tagów: [oficjalny obraz PHP](https://github.com/docker-library/official-images/blob/master/library/php) i [oficjalny obraz MariaDB](https://github.com/docker-library/official-images/blob/master/library/mariadb).

Aby podczas aktualizacji pobrać bieżące obrazy tych gałęzi (po wykonaniu kopii i sprawdzeniu aktualizacji na osobnej instalacji):

```sh
docker compose stop web
docker compose pull db
docker compose build --pull
docker compose up -d --wait
```

Samo `up --build` może wykorzystać lokalnie zapisany obraz bazowy. Aktualizacja silników nie zmienia numeru aplikacji ani rewizji jej schematu.

Aktualizację wersji silnika wprowadza nowe, przetestowane wydanie programu. Nie podmieniaj samodzielnie tagu bazy na nowszy ani starszy na tym samym katalogu danych. Aktualizacja struktury aplikacji i aktualizacja silnika to odrębne operacje. Same poprawki obrazu pod tym samym tagiem również sprawdź na kopii przed odtworzeniem usług.

### Pierwsze przejście z wcześniejszych wersji do 1.0

Zachowaj `app_data` i zrób kopię bazy oraz konfiguracji według instrukcji powyżej. Zatrzymaj WWW, pobierz źródła wydania 1.0 i uruchom aktualizację podaną wyżej. Migracja `0001_baseline.php` sprawdza istniejący schemat, uzupełnia dawną preferencję motywu w razie potrzeby i rejestruje wersjonowany schemat 1. Nie usuwa zamówień, kont ani raportów. Lokalna wcześniejsza baza używająca `latest` działała już na MariaDB 13.0.2, więc wybór gałęzi 13 zachowuje główną wersję silnika, ale kolejne pobrania mogą zmienić jego podgałąź. Dla innej wcześniejszej wersji MariaDB najpierw zaplanuj osobną migrację silnika na kopii.

### Kontrola wersji i migracji

Kliknij informację w stopce aplikacji albo sprawdź rejestr z terminala:

```sh
docker compose exec -T web php -r 'require "/var/www/db.php"; require "/var/www/version.php"; echo json_encode(versionInfo(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;'
docker compose exec -T web php /var/www/migrate.php
```

Drugie polecenie jest idempotentne dla aktualnej bazy. Nie zastępuje kopii przed aktualizacją. W przypadku błędu zajrzyj do `docker compose logs web` i nie poprawiaj ręcznie sum kontrolnych. Powrót po zmianie schematu wymaga odpowiedniego starego wydania oraz odtworzenia kopii bazy, zamiast uruchamiania starego kodu na nowszej strukturze.

## Diagnostyka

```sh
docker compose ps -a
docker compose logs --tail=100 setup db web
```

- **Brak połączenia z Dockerem:** uruchom Docker Desktop lub usługę silnika Docker.
- **Zajęty port:** zmień `APP_PORT` i uruchom ponownie.
- **Baza nie przechodzi testu gotowości:** zajrzyj do logów `db`; sprawdź miejsce na dysku i zachowanie właściwego katalogu `config` z hasłami.
- **Błąd logowania do aplikacji:** odczytaj hasło poleceniem z sekcji pierwszego logowania. Edycja pliku `admin-password` nie resetuje hasła konta już istniejącego w bazie.
- **Po aktualizacji widzisz stary interfejs:** odśwież stronę, a w razie potrzeby zamknij i ponownie otwórz okno PWA.

## Podman i wcześniejsza wersja lokalna

Ten sam `compose.yaml` można uruchomić przez **`podman compose`** z zainstalowanym dostawcą Compose. Klient Docker Compose współpracujący z Podmanem potrzebuje działającego gniazda API; na typowym Linuksie z systemd uruchamia je `systemctl --user start podman.socket`. Obsługa szczegółów zależy od dostawcy Compose. Testy przygotowanego pliku wykonano dostępnym klientem Docker Compose z backendem Podmana.

Skrypty `scripts/start.sh` i `scripts/stop.sh` są skrótami do `podman compose` i korzystają z tych samych katalogów bind mountów. Wymagają działającego dostawcy Compose i API Podmana. Nie tworzą nazwanych wolumenów.

### Przejście ze starszych nazwanych wolumenów

Nowy Compose nie przejmuje danych poprzedniej instalacji automatycznie. Przed zmianą pliku Compose zachowaj poprzednią wersję, zrób nią zrzut bazy i archiwum konfiguracji według dotychczasowej instrukcji. W nowej wersji ustaw pusty `APP_DATA_DIR`, odtwórz konfigurację, uruchom samą bazę i zaimportuj SQL zgodnie z sekcją odtwarzania. Dopiero potem uruchom WWW. Nie usuwaj starych wolumenów przed sprawdzeniem danych i logowania.

Dla bardzo starego skryptu natywnego Podmana (`drukarnia-db`, `drukarnia-web`) hasło admina było w `.env`, a dane w wolumenie `drukarnia-db`. Przy migracji zachowaj tę samą wartość `ADMIN_PASSWORD` w nowym `config/admin-password` i przenieś bazę logicznym zrzutem; nie wystarczy wygenerować nowego hasła plikowego, bo hasz konta w importowanej bazie pozostaje stary.

## Źródła obrazów

- [PHP/Apache — Docker Official Image](https://hub.docker.com/_/php), kod opakowania: [docker-library/php](https://github.com/docker-library/php).
- [MariaDB — Docker Official Image](https://hub.docker.com/_/mariadb), kod opakowania: [MariaDB/mariadb-docker](https://github.com/MariaDB/mariadb-docker).

Są to publicznie utrzymywane źródła, z których korzysta aplikacja; nie dodano nowych bibliotek aplikacyjnych. Zaufanie do źródła nie gwarantuje braku podatności. Nie przeprowadzono pełnego audytu reputacji wszystkich autorów ani bezpieczeństwa wszystkich składników obrazów. Nie twierdzimy, że projekty lub ich twórcy są wolni od wszelkich kontrowersji.

## Licencja i udostępnianie zmienionej aplikacji

Bieżące źródła repozytorium są oznaczone **AGPL-3.0-only**. Przy udostępnianiu zmodyfikowanej wersji przez sieć zapewnij użytkownikom widoczną możliwość bezpłatnego otrzymania odpowiednich źródeł działającej wersji, wraz z potrzebnymi skryptami budowania i instalacji. Nie publikuj danych z `app_data`, haseł ani kopii bazy. Szczegóły są w [licensing.md](licensing.md), a wynik przeglądu komponentów w [LICENSE_AUDIT.md](LICENSE_AUDIT.md).
