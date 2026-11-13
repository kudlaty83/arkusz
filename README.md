# Arkusz 1.0 — obsługa drukarni

Arkusz to przeglądarkowa aplikacja do przyjmowania zamówień i organizacji pracy drukarni: od ustaleń z klientem, przez przygotowalnię i druk, po introligatornię i wydanie. Interfejs jest w języku polskim. Aplikacja działa jako strona internetowa i może być instalowana w Chrome jako PWA.

**Aplikacja ma charakter testowy i prototypowy. Powstała w większości z użyciem generatywnej AI i nie jest kompletnym, gotowym do wdrożenia programem dla drukarni.** Projekt jest przede wszystkim zaproszeniem do kontaktu, wymiany pomysłów i samodzielnego dalszego rozwoju.

Wersja **1.0.0** oznacza początek wersjonowania, a nie deklarację gotowości produkcyjnej. API: **1.0**. Schemat bazy: **rewizja 1**. Kolejne wydania aktualizują istniejącą instalację przez wersjonowane migracje, z zachowaniem danych.

**Instalacja, pierwsze logowanie, kopie zapasowe i aktualizacje: [install.md](install.md).**

## Zamówienia i specyfikacja

- Klienci, dane kontaktowe, terminy, priorytety oraz statusy realizacji.
- Wiele produktów w jednym zamówieniu, np. książka i plakat.
- Osobne części technologiczne produktu, np. okładka, środek i wkładka.
- Rodzaj produktu, nakład, format netto, liczba stron, spady i uzgodniona cena netto całej pozycji.
- Papier, gramatura i technika druku osobno dla każdej części.
- Offset: 0–5 kolorów na stronę, z nazwami farb, np. CMYK i Pantone; co najmniej jedna strona zadrukowana.
- Cyfra: BW, pełny kolor lub brak druku, niezależnie dla obu stron.
- Osobne, wielowierszowe dodatkowe informacje do zamówienia, produktu i części oraz notatki wewnętrzne zamówienia.

## Cechy i uszlachetnienia

Produkty i części mogą mieć wiele cech, również kilka wystąpień tego samego rodzaju: przetłoczenia, wykrojenia, wycięcia, lakierowanie, foliowanie, perforację, bigowanie, zaokrąglenia narożników lub oprawę. Każde wystąpienie ma własne parametry i uwagi.

Parametry mogą być tekstem, liczbą z jednostką lub wyborem tak/nie. Katalog cech określa, czy dana cecha dotyczy całego produktu, części czy obu zakresów.

## Produkcja i pracownicy

- Role BOK, grafika, drukarza, introligatora, kierownika produkcji i administratora; pracownik może mieć wiele ról i stanowisk.
- Zadania dla produktu lub części, z operacją, stanowiskiem, instrukcją, terminem, planowaną ilością i jednostką.
- Wielu wykonawców zadania oraz wskazanie realizowanych cech.
- Zależności etapów: rozpoczęcie pracy wymaga zakończenia poprzedników.
- Tablica produkcji, kolejka własnych zadań i filtrowanie po stanowiskach.
- Przyrostowe raporty wykonania, odpadu i czasu pracy; blokady z uzasadnieniem.
- Historia statusów zamówienia, raportów i przydziałów wykonawców.

Pracownik produkcyjny raportuje wyłącznie przydzielone mu zadania. BOK i kierownik planują produkcję. Administrator zarządza kontami, rolami i uprawnieniami.

## Edycja i historia

Przyciski **Edytuj zamówienie**, **Edytuj produkt** i **Edytuj część** otwierają formularz aktualnej specyfikacji. Można zmieniać parametry oraz dodawać produkty i części. Edycja zachowuje identyfikatory, przydziały i raporty. Zapis jest transakcyjny i wykrywa równoczesną edycję tego samego zamówienia.

Zmiana nakładu lub terminu zamówienia nie przelicza automatycznie planu istniejących zadań. Nie ma usuwania istniejących produktów i części z interfejsu. Cech powiązanych z zadaniami nie można usuwać ani zmieniać ich rodzaju; ich parametry i uwagi są edytowalne.

Dane klienta, papieru i rodzaju produktu są zapisywane w zamówieniu jako migawki. Późniejsza zmiana katalogu nie zmienia automatycznie tych danych w przyjętym zamówieniu.

## Słowniki

Edytor **Słowniki** obejmuje dziewięć kategorii:

- Rodzaje papieru oraz papiery i gramatury.
- Rodzaje produktów.
- Role i uprawnienia.
- Stanowiska pracy.
- Operacje produkcyjne.
- Statusy zamówień.
- Cechy i uszlachetnienia.
- Parametry cech.

Wpisy można dodawać, edytować oraz, jeśli mają pole aktywności, wyłączać i ponownie włączać. Nieaktywne wpisy pozostają dostępne w historii. Kody istniejących wpisów są stałe. Systemowe statusy oraz pełny dostęp administratora są chronione.

## Wygląd i PWA

Każdy użytkownik wybiera własny motyw: **Zielony**, **Beżowy**, **Jasny beż**, **Szary** lub **Czarny**. Wybór oferuje podgląd, a zapisane ustawienie odtwarza się po zalogowaniu na innym urządzeniu.

Interfejs dostosowuje się do mniejszych ekranów. **Aplikację można zainstalować także na telefonie jako PWA** — na Androidzie oraz na iPhonie przez dodanie do ekranu początkowego. PWA otwiera aplikację w osobnym oknie. Telefon musi mieć dostęp do serwera przez HTTPS; instrukcja znajduje się w [install.md](install.md#dostęp-z-innych-urządzeń-i-instalacja-na-telefonie). Praca z zamówieniami i zapisy wymagają połączenia z serwerem; zamówienia nie są przechowywane w pamięci podręcznej przeglądarki do pracy offline.

## Technologia i obecne ograniczenia

HTML, CSS i JavaScript bez frameworka, procesu budowania frontendu, bibliotek npm/Composer, CDN i zewnętrznych fontów. Backend i API: PHP **8.4.x** (`php:8.4-apache`). Baza: MariaDB **13.x** (`mariadb:13`). Pliki aplikacji, konfiguracja, sesje i dane bazy znajdują się w oddzielnych podkatalogach `app_data` na dysku hosta; Compose używa bind mountów zamiast nazwanych wolumenów. Kompletny schemat znajduje się w [baza.sql](baza.sql). Aplikacja korzysta z sesji, ochrony CSRF i uprawnień sprawdzanych przez API.

Nie zaimplementowano przesyłania i akceptacji plików, magazynu, kalkulacji kosztów, fakturowania, obsługi wysyłek, korekt raportów ani edycji planowanych ilości już istniejących zadań. Akceptację można obecnie zaplanować jako zadanie. Dane plików mają przygotowaną tabelę w schemacie, ale nie mają jeszcze interfejsu. Aplikacja nie jest systemem księgowym.

## Licencja i pochodzenie kodu

Licencja źródeł projektu: **GNU Affero GPL wersja 3 — AGPL-3.0-only**, w zakresie praw, którymi udostępniający może rozporządzać. Pełny tekst: [LICENSE](LICENSE). Zakres licencji, obowiązek udostępnienia źródeł zmodyfikowanej wersji użytkownikom sieciowym i pochodzenie kodu: [licensing.md](licensing.md). Wynik przeglądu zgodności zależności: [LICENSE_AUDIT.md](LICENSE_AUDIT.md).

PHP, Apache, MariaDB i pozostałe składniki obrazów kontenerowych mają własne licencje. Licencja projektu nie zastępuje licencji tych komponentów.

## Wersje i aktualizacje

Wersja programu i rewizja schematu są widoczne w stopce. Kliknięcie otwiera szczegóły: wersję API, faktycznie działające PHP i MariaDB oraz wydanie schematu. Te same informacje udostępnia zalogowanemu użytkownikowi `GET /api.php?action=version`. Wersja silnika MariaDB i rewizja struktury danych są niezależne. Historia zmian znajduje się w [CHANGELOG.md](CHANGELOG.md), a zasady utrzymania wydań w [RELEASING.md](RELEASING.md).
