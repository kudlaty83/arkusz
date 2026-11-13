# Historia wydań

**Arkusz jest aplikacją testową i prototypową, powstałą w większości z użyciem generatywnej AI. Nie jest kompletnym programem dla drukarni. Projekt stanowi zaproszenie do kontaktu, wymiany pomysłów i samodzielnego dalszego rozwoju; numer 1.0.0 nie oznacza gotowości produkcyjnej.**

## 1.4.0 — 2026-11-13

Wydanie testowe, powstałe w większości z użyciem AI, przeznaczone do oceny i dalszego rozwoju. Program: **1.4.0**; API: **1.4**; schemat: **rewizja 6**.

- Anulowanie zamówienia anuluje niedokończone etapy i blokuje planowanie, przydziały oraz raporty. Anulowanie etapu obejmuje jego niedokończonych następców. Obie operacje wymagają podglądu, potwierdzenia i aktualnego planu; zakończone zadania oraz raporty pozostają zachowane.
- Zwiększenie nakładu wstrzymuje następców ponownie otwartego etapu także wtedy, gdy mają nadprodukcję. Zachowuje wykonanie do weryfikacji.
- Ilości proporcjonalne liczone ze stałej podstawy, bez kumulowania zaokrągleń; ilości stałe z szablonów pozostają stałe. Edytor pokazuje tryb, podstawę i możliwość jej jawnego przeliczenia. Późniejsze zmiany szablonu nie zmieniają reguły istniejącego zadania.
- Trwały, atomowy limit logowania w MariaDB, niezależny od ciasteczek, z limitami konta i źródła, HTTP 429 i `Retry-After`. Nagłówki proxy uwzględniane wyłącznie dla jawnie wskazanych adresów `TRUSTED_PROXY_IPS`.
- Prywatne katalogi haseł `0700`, pliki `0400`, rozdzielone pomiędzy `db`, `init` i `web`. WWW nie otrzymuje hasła roota MariaDB ani hasła pierwszego administratora. Nowa usługa `init` wykonuje migracje przed uruchomieniem WWW. Istniejące hasła są zachowane.
- Migracja 0006 zachowuje planowane ilości i raporty, odtwarza pewne reguły z migawek szablonów, pozostałe oznacza do jawnej weryfikacji. Naprawia aktywne zadania anulowanych zamówień i następników anulowanych etapów z wpisem do historii. Dotychczasowe migracje pozostają niezmienione.
- API: `preview_order_status` / `order_status`, `preview_report` / `report` wymagają tokenu przy anulowaniu. Zadania zwracają tryb i podstawę ilości; starsze zadania wymagają jawnego wyboru reguły przed zmianą nakładu.

Aktualizacja: kopia bazy i całego `config`, zatrzymanie WWW, przebudowanie oraz uruchomienie Compose według `install.md`. Wycofanie wymaga odtworzenia kopii wraz ze źródłami poprzedniego wydania.

## 1.3.0 — 2026-11-13

Wydanie testowe. Program: **1.3.0**; API: **1.3**; schemat: **rewizja 5**. Obrazy: PHP `8.4-apache`, MariaDB `13`.

- Edycja etapów produkcji: operacja, część, stanowisko, ilość i jednostka, kolejność, daty, parametry, instrukcje, wykonawcy, cechy i poprzednicy. Serwerowy podgląd i osobne potwierdzenie; ochrona wersji i pełny audyt zmian.
- Kontrola cykli oraz przynależności części, cech, poprzedników i wykonawców. Zmiana technologiczna tworzy rewizję i wstrzymuje etap oraz następców. Zwiększenie ilości zakończonego etapu otwiera go z zachowaniem wykonania. Starsze raporty zachowują nazwy operacji i jednostki na podstawie migawek sprzed edycji. Zmiany planu lub raport po podglądzie unieważniają zgodę.
- Słownik rodzajów części, z początkowymi wpisami Okładka, Środek, Wkładka i Całość. Istniejące części pozostają bez przypisanego rodzaju; jego wybór nie zmienia dotychczasowego planu.
- Menu Szablony etapów dla planistów: wersjonowane szablony rodzaju części, z opcjonalnym ograniczeniem produktu i techniki druku; do 100 etapów, stałe ilości lub współczynniki nakładu, terminy względem zamówienia, instrukcje, parametry i zależności.
- Zastosowanie szablonu w karcie części po podglądzie, z wyborem wykonawców i poprzednika pierwszego etapu. Zapis atomowy, migawka użytej wersji, blokada ponownego zastosowania tego samego szablonu do tej samej części. Zmiana szablonu nie zmienia istniejących zadań. Listy szablonów są stronicowane.
- Migracja `0005_production_templates.php`: pięć nowych tabel, opcjonalny rodzaj części, źródło szablonu w zadaniu i klucze obce. Zachowuje wszystkie dotychczasowe rekordy i raporty; wcześniejsze migracje pozostają niezmienione.
- API: `GET task`, `POST preview_task_update` / `update_task`; `GET templates` / `template`, `POST save_template`, `preview_apply_template` / `apply_template`. Potwierdzenia wiążą treść i aktualny plan, są jednorazowe i ważne 15 minut. Dotychczasowe tworzenie i raportowanie zadań pozostaje dostępne.

Aktualizacja z 1.2.0: wykonaj kopię bazy i konfiguracji, zatrzymaj WWW, przebuduj obraz i uruchom Compose według `install.md`. W razie wycofania odtwórz zweryfikowaną kopię bazy i konfiguracji wraz ze źródłami poprzedniego wydania; nie uruchamiaj starszej aplikacji na schemacie 5.

## 1.2.0 — 2026-11-13

Wydanie testowe. Program: **1.2.0**; API: **1.2**; schemat: **rewizja 4**. Obrazy: PHP `8.4-apache`, MariaDB `13`.

- Edytor klientów z adresem, kontaktami, NIP-em, uwagami i aktywnością. Zapis chroniony wersją rekordu; migawki klientów w przyjętych zamówieniach pozostają bez zmian.
- Strony po 10/25/50/100 wpisów, licznik wyników, filtr aktywności i wyszukiwanie prefiksów po stronie serwera. Stabilne sortowanie po nazwie i ID. Bootstrap nie zawiera pełnej listy klientów; wybór w zamówieniu pobiera po 10 wyników, zachowując dotychczasowy wybór.
- Edycja loginu, danych, ról, stanowisk i aktywności pracownika. Stronicowana lista kont, także nieaktywnych dla administratora. Zachowanie przydziałów i raportów, ochrona ostatniego administratora i zakaz wyłączania własnego konta.
- Zmiana własnego hasła z weryfikacją obecnego, ustawienie hasła innego konta przez administratora i unieważnianie sesji. Hasła od 12 znaków, do 72 bajtów UTF-8; przekroczenie limitu jest odrzucane zamiast obcinania. Pola haseł i skróty nie są zwracane przez API.
- Migracja `0004_customers_users.php`: wersje rekordów klientów i kont, licznik sesji oraz indeksy wyszukiwania i sortowania. Istniejące dane i hasła pozostają bez zmian; dotychczasowe sesje zachowują ważność do zmiany danych uwierzytelniających lub wyłączenia konta.
- API: stronicowane `GET customers` i `GET users` (`page`, `page_size`, `q`, `active`), pojedynczy `GET customer&id=…` i administratorski `GET user&id=…`; zapisy `POST update_customer`, `update_user`, `change_password`, `reset_password`. Edycje wymagają wersji rekordu. Dotychczasowe `POST customer` i `create_user` nadal tworzą rekordy. Integracje pobierają klientów przez paginowany endpoint zamiast `bootstrap.customers`.

Aktualizacja z 1.1.1: kopia bazy i konfiguracji, zatrzymanie WWW, przebudowanie i start Compose według `install.md`. Migracja dodaje kolumny z wartością 1 oraz indeksy; nie resetuje kont, zamówień ani haseł. Powrót do wcześniejszego wydania wymaga odtworzenia zweryfikowanej kopii bazy i konfiguracji wraz z odpowiednimi źródłami — schema 4 blokuje starszy program.

## 1.1.1 — 2026-11-13

Wydanie testowe. Program: **1.1.1**; API: **1.1**; schemat: **rewizja 3**. Obrazy: PHP `8.4-apache`, MariaDB `13`.

- Jawny katalog roboczy `/var/www` w obrazie oraz usługach `setup` i `web`. Usuwa konflikt odziedziczonego `/var/www/html` z montowaniem katalogu aplikacji tylko do odczytu przy uruchamianiu przez Docker/runc.
- Test uruchomienia PHP z katalogiem `/var/www` tylko do odczytu, bez podkatalogu `html`, oraz kontrola katalogu roboczego uruchomionej usługi.
- Migracja `0003_container_workdir.php` waliduje istniejący schemat bez DDL i aktualizuje rejestr wydania. Dotychczasowe migracje pozostają niezmienione.

Aktualizacja z 1.1.0: zachowaj `APP_DATA_DIR`, wykonaj kopię bazy i konfiguracji, zatrzymaj WWW i przebuduj obrazy zgodnie z `install.md`. W razie wycofania odtwórz kopię bazy i konfiguracji wraz ze źródłami poprzedniego wydania; schemat 3 blokuje start starszego programu. Doraźnie w starszej instalacji wystarczy `working_dir: /var/www` w usłudze `web` i odtworzenie jej kontenera.

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
