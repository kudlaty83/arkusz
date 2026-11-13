# Arkusz 1.2 — obsługa drukarni

Arkusz to przeglądarkowa aplikacja do przyjmowania zamówień i organizacji pracy drukarni: od ustaleń z klientem, przez przygotowalnię i druk, po introligatornię i wydanie. Interfejs jest w języku polskim. Aplikacja działa jako strona internetowa i może być instalowana w Chrome jako PWA.

**Aplikacja ma charakter testowy i prototypowy. Powstała w większości z użyciem generatywnej AI i nie jest kompletnym, gotowym do wdrożenia programem dla drukarni.** Projekt jest przede wszystkim zaproszeniem do kontaktu, wymiany pomysłów i samodzielnego dalszego rozwoju.

Aktualna wersja testowa: **1.2.0**. Numer wersji nie oznacza gotowości produkcyjnej. API: **1.2**. Schemat bazy: **rewizja 4**. Kolejne wydania aktualizują istniejącą instalację przez wersjonowane migracje, z zachowaniem danych.

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

## Klienci

Zakładka **Klienci** pozwala dodawać i edytować nazwę, NIP, e-mail, telefon, adres, uwagi oraz aktywność klienta. Lista ma strony po **10, 25, 50 lub 100 wpisów**, licznik wyników i filtr aktywności. Wyszukiwanie obejmuje początek nazwy, NIP-u, e-maila lub telefonu. Sortowanie po nazwie i identyfikatorze zapewnia stałą kolejność także przy powtarzających się nazwach.

Stronicowanie i wyszukiwanie wykonuje serwer. Aplikacja nie pobiera całej kartoteki przy logowaniu. Również wybór klienta w zamówieniu korzysta z wyszukiwania i stron po 10 wyników; już wybrany klient pozostaje wybrany przy zmianie strony. Nieaktywny klient nie może być wskazany w nowym zamówieniu, ale pozostaje w historii. Edycja kartoteki nie przepisuje migawek danych w istniejących zamówieniach. Równoczesna edycja jest wykrywana przy zapisie.

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

## Konta i hasła

W zakładce **Zespół** administrator dodaje i edytuje pracowników: login, imię i nazwisko, role, stanowiska oraz aktywność. Konta można wyszukiwać i przeglądać stronami; administrator widzi również nieaktywne konta. Wyłączenie konta zachowuje zadania, przydziały i raporty, a blokuje dostęp. Nie można wyłączyć własnego konta ani odebrać roli ostatniemu aktywnemu administratorowi. Zapis wykrywa równoczesną edycję konta.

Każdy użytkownik ma opcję **Zmień moje hasło** w panelu bocznym. Podaje obecne hasło i dwukrotnie nowe. Bieżąca sesja pozostaje aktywna, a pozostałe sesje wygasają. Administrator może **Ustawić nowe hasło** innemu pracownikowi w zakładce Zespół; powoduje to wygaśnięcie wszystkich sesji tego konta. Wyłączenie i ponowne włączenie konta nie przywraca wcześniejszych sesji. Hasła są przechowywane jako skróty, a API nie zwraca ich ani ich skrótów.

## Edycja i historia

Przyciski **Edytuj zamówienie**, **Edytuj produkt** i **Edytuj część** otwierają formularz aktualnej specyfikacji. Można zmieniać parametry oraz dodawać produkty i części. Edycja zachowuje raporty i przydziały; usunięte elementy są archiwizowane. Zapis zamówienia i planu jest transakcyjny. Formularz ostrzega o skutkach zmian, a przed zapisem pokazuje ich dokładne podsumowanie z osobnym przyciskiem **Zatwierdź i zapisz zmiany**. Można wrócić do edycji bez zapisywania.

Po zatwierdzeniu aplikacja stosuje następujące reguły:

- Nakład skaluje planowane ilości nieanulowanych zadań proporcjonalnie do dotychczasowego planu, z zaokrągleniem w górę. Dotyczy to także innych jednostek raportowania. Zakończone zadanie z brakującą ilością wraca do stanu wstrzymanego; dotychczasowe wykonanie jest zachowane. Spadek nakładu nie kończy automatycznie trwających zadań ani nie usuwa nadprodukcji.
- Zmiana terminu przesuwa terminy i planowane rozpoczęcia niezakończonych zadań o tę samą różnicę czasu, zachowując odstępy. Przy pierwszym nadaniu terminu zadania otrzymują termin zamówienia; jego usunięcie usuwa terminy zadań. Daty faktycznego wykonania pozostają historyczne.
- Zmiana parametrów technologicznych produktu, części lub powiązanej cechy wstrzymuje dotknięte zadania i etapy zależne oraz tworzy nową rewizję ich planu. Dotyczy także zadań zakończonych: wykonanie nowej specyfikacji liczy się od zera, a wcześniejsze raporty pozostają w historii. Same uwagi nie zerują wykonania. Zamówienie gotowe lub zamknięte, w którym wznowiono zadania, wraca do produkcji.
- Usunięcie produktu, części lub powiązanej cechy anuluje niezakończone zadania objęte zmianą i niezakończonych następców. Usunięcie części obejmuje również zadania całego produktu. Zadania zakończone i raporty pozostają w historii. Zmiana rodzaju cechy zachowuje jej dawny rekord i przenosi powiązanie zadania do nowej specyfikacji.
- Nowe produkty, części i cechy wymagają doboru operacji, stanowisk i wykonawców w karcie zamówienia; aplikacja informuje o tym w podsumowaniu. Nie dobiera automatycznie technologii produkcji.

Zmiana planu lub raport po podglądzie wymaga ponownego podsumowania i potwierdzenia. Zatwierdzone zmiany są widoczne w historii zamówienia i dzienniku zadań. Użytkownik zmieniający plan potrzebuje uprawnienia do planowania pracy.

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

Nie zaimplementowano przesyłania i akceptacji plików, magazynu, kalkulacji kosztów, fakturowania, obsługi wysyłek, korekt raportów ani osobnego edytora planowanej ilości pojedynczego zadania poza przeliczaniem przy zmianie nakładu zamówienia. Akceptację można obecnie zaplanować jako zadanie. Dane plików mają przygotowaną tabelę w schemacie, ale nie mają jeszcze interfejsu. Aplikacja nie jest systemem księgowym.

## Licencja i pochodzenie kodu

Licencja źródeł projektu: **GNU Affero GPL wersja 3 — AGPL-3.0-only**, w zakresie praw, którymi udostępniający może rozporządzać. Pełny tekst: [LICENSE](LICENSE). Zakres licencji, obowiązek udostępnienia źródeł zmodyfikowanej wersji użytkownikom sieciowym i pochodzenie kodu: [licensing.md](licensing.md). Wynik przeglądu zgodności zależności: [LICENSE_AUDIT.md](LICENSE_AUDIT.md).

PHP, Apache, MariaDB i pozostałe składniki obrazów kontenerowych mają własne licencje. Licencja projektu nie zastępuje licencji tych komponentów.

## Wersje i aktualizacje

Wersja programu i rewizja schematu są widoczne w stopce. Kliknięcie otwiera szczegóły: wersję API, faktycznie działające PHP i MariaDB oraz wydanie schematu. Te same informacje udostępnia zalogowanemu użytkownikowi `GET /api.php?action=version`. Wersja silnika MariaDB i rewizja struktury danych są niezależne. Historia zmian znajduje się w [CHANGELOG.md](CHANGELOG.md), a zasady utrzymania wydań w [RELEASING.md](RELEASING.md).
