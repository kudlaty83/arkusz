# Przegląd zgodności z AGPL-3.0-only

Data: 2026-11-13. Raport dotyczy wskazanych poniżej, sprawdzonych obrazów. Konfiguracja obecnie śledzi ruchome tagi `php:8.4-apache` i `mariadb:13`; raport nie stanowi audytu przyszłych aktualizacji tych obrazów. Zakres: źródła Arkusza, konfiguracja budowania oraz odczyt faktycznie używanych obrazów PHP 8.4.27/Apache i MariaDB 13.0.2.

**Wniosek:** w sprawdzonym zakresie nie znaleziono zależności blokującej udostępnianie własnego kodu Arkusza na AGPL-3.0-only. Wniosek dotyczy tej architektury i obecnych zależności, nie prawa do przelicencjonowania całych obrazów systemowych ani pełnego audytu prawnego ich wszystkich pakietów.

## Sprawdzone składniki

| Składnik | Ustalenie | Znaczenie dla projektu |
|---|---|---|
| PHP, HTML, CSS, JavaScript, SQL, migracje, skrypty i dokumentacja Arkusza | Własne źródła w repozytorium; kod wytworzony z pomocą AI. Brak vendora i skopiowanych nagłówków bibliotek. | Mogą zostać oznaczone AGPL w zakresie praw udostępniającego; kwestia ochrony wyników AI jest opisana w `licensing.md`. |
| Zależności aplikacyjne | Brak `composer.json`, `composer.lock`, `package.json`, lockfile npm i dołączonych pakietów Composer/npm. | Brak dodatkowych licencji bibliotek aplikacyjnych do pogodzenia. |
| Fonty i grafika | CSS używa wyłącznie lokalnych fontów (`local(...)`); nie ma plików fontów ani pobierania z CDN. Ikony SVG i PNG przygotowano w projekcie. | Nie wykryto dołączonej zewnętrznej czcionki ani zakupionej grafiki ograniczającej relicencjonowanie. Nie jest to audyt praw do fontów zainstalowanych na komputerze użytkownika. |
| PHP 8.4.27 i Zend Engine | Archiwum użytego obrazu zawiera PHP License 3.01 i Zend Engine License 2.00. Aktualna informacja PHP dopuszcza również zastosowanie nowszych licencji do dawnych wersji. | Nie kopiujemy ani nie przelicencjonowujemy interpretera jako części Arkusza. Zachowujemy jego teksty licencji i obowiązkowe oznaczenia. |
| PDO / PDO MySQL / mysqlnd | `php --ri pdo_mysql` wskazuje `Client API version => mysqlnd 8.4.27`; nagłówki PDO i mysqlnd w użytym źródle odwołują się do PHP License 3.01. `ldd pdo_mysql.so` nie wykazuje `libmysqlclient`. | Aplikacja korzysta ze standardowego PDO oraz protokołu serwera; nie dołączono biblioteki klienta MySQL GPLv2-only do kodu Arkusza. |
| Apache HTTP Server | Pakiet obrazu zawiera Apache-2.0 i informacje o dodatkowych licencjach poszczególnych plików. | Osobny składnik środowiska; nie zmieniamy jego licencji. Apache-2.0 jest zgodna z rodziną GPLv3/AGPLv3 przy zachowaniu właściwych wymagań. |
| MariaDB Server 13.0.2 | GPLv2; pakiet zawiera osobne informacje dla dodatkowych składników. Serwer działa w oddzielnym kontenerze. | Komunikacja z serwerem po protokole nie przenosi GPLv2 na aplikację. Nie wolno przedstawiać samego serwera jako AGPL. |
| `websockets` w testach przeglądarkowych | Opcjonalna zależność deweloperska; zainstalowana wersja 15.0.1 ma BSD-3-Clause. Repozytorium nie zawiera jej kodu. Inne importy testów należą do biblioteki standardowej Pythona lub lokalnych testów. | BSD-3-Clause nie blokuje AGPL; przy ewentualnym dołączeniu biblioteki należy zachować jej noty licencyjne. Nie jest potrzebna do uruchamiania aplikacji. |
| Docker/Podman, Compose i system operacyjny obrazów | Narzędzia wykonawcze oraz agregat pakietów, nie własny kod Arkusza. | Ich warunki pozostają odrębne. Zmiana etykiety OCI dotyczy aplikacji, nie wszystkich plików systemu w obrazie. |

## Dlaczego stare licencje PHP i GPLv2 serwera nie są tutaj blokadą

Nie należy utożsamiać uruchamiania skryptu w interpreterze z kopiowaniem źródeł tego interpretera do skryptu. GNU opisuje tę różnicę w FAQ, z zastrzeżeniem, że dołączanie bibliotek przez dodatkowe wiązania wymaga osobnej oceny. Tutaj sprawdzono także faktyczny PDO/mysqlnd, brak `libmysqlclient` w powiązaniach modułu oraz oddzielny serwer bazy. Dokumentacja MariaDB wprost omawia połączenie z serwerem i użycie PDO/mysqlnd bez przenoszenia licencji serwera na aplikację.

Nie zakładamy, że historyczna PHP License 3.01 jest zgodna z GPL. Oficjalna strona PHP obecnie dopuszcza użycie wcześniejszych wydań na PHP License 4, równoważnej BSD-3-Clause, oraz opisuje nową licencję Zend. Nie zastępujemy jednak w obrazie jego istniejących not i nie kopiujemy kodu PHP do Arkusza. Ewentualne przyszłe rozszerzenia natywne, inne sterowniki i cudzy kod wymagają ponownego sprawdzenia.

## Sprawdzenie obrazów i dystrybucji

Odczytano archiwum `/usr/src/php.tar.xz` oraz nagłówki `LICENSE`, `Zend/LICENSE`, `ext/pdo/php_pdo.h` i `ext/mysqlnd/mysqlnd.h`. Sprawdzono informacje pakietów `/usr/share/doc/apache2/copyright` oraz `/usr/share/doc/mariadb-server/copyright`. Nie usuwamy tych dokumentów z obrazów.

Przegląd dotyczył lokalnych obrazów o digestach:

- `docker.io/library/php:8.4.27-apache`: `sha256:7c3d8b9e579e29cdf2fbce05649f8427840798800d4d7c1528e0bf17dec7354c`.
- `docker.io/library/mariadb:13.0.2`: `sha256:4817c4f8ac0c9141f6fe42337ec256b7a88dc9461e37be725528688066567e9d`.

To identyfikatory lokalnie sprawdzonych obrazów, nie nowe przypięcie konfiguracji do digestów. Tag może zostać przebudowany przez dostawcę. Nie przeprowadzono pełnego SBOM ani analizy każdej biblioteki systemowej, jej modyfikacji i obowiązków dostarczenia źródeł przy dystrybucji gotowego obrazu. Publikowanie obrazu wymaga zachowania not, licencji i spełnienia obowiązków źródłowych odpowiednich komponentów; samo dołączenie AGPL aplikacji nie zastępuje tych obowiązków. Oprogramowanie w obrazach nie staje się automatycznie AGPL.

## Obowiązki po zmianie na AGPL

- Przy dystrybucji aplikacji dołącz pełny tekst AGPL i odpowiedni kod źródłowy na zasadach tej licencji.
- Przy udostępnianiu zmodyfikowanej wersji przez sieć zapewnij widoczną ofertę bezpłatnego otrzymania źródeł dokładnie tej wersji, zgodnie z sekcją 13. Nie wystarczy oznaczenie SPDX ani link do innego wydania.
- Do odpowiednich źródeł zaliczają się potrzebne skrypty budowania, konfiguracji i instalacji. Nie publikuj `app_data`, haseł, sesji, kopii SQL ani danych klientów. Zachowaj szablony konfiguracji.
- Nie zmieniaj ani nie usuwaj cudzych not i licencji. Kopie uprzednio udostępnione na GPLv3 zachowują przyznane im prawa; zmiana nie działa wstecz.
- Przy wdrożeniu zmodyfikowanej wersji oferta źródeł musi wskazywać odpowiadający jej commit lub archiwum wydania.

## Źródła pierwotne

- [GNU AGPLv3 — pełna licencja, w tym sekcja 13](https://www.gnu.org/licenses/agpl-3.0.html).
- [GNU FAQ — interpreter o licencji niezgodnej z GPL](https://www.gnu.org/licenses/gpl-faq.html#InterpreterIncompat).
- [GNU FAQ — zgodność licencji, również AGPLv3](https://www.gnu.org/licenses/gpl-faq.html#AllCompatibility).
- [PHP — informacje licencyjne i możliwość stosowania wersji 4 do starszych wydań](https://www.php.net/license/).
- [PHP 8.4.27 — rzeczywisty tekst LICENSE w oznaczonych źródłach](https://github.com/php/php-src/blob/php-8.4.27/LICENSE).
- [PHP — opis mysqlnd](https://www.php.net/manual/en/mysqlnd.overview.php).
- [MariaDB — FAQ licencyjne, serwer i sterowniki](https://mariadb.com/docs/general-resources/community/community/faq/licensing-questions/licensing-faq).
- [Apache — zgodność Apache-2.0 z GPLv3](https://apache.org/licenses/GPL-compatibility.html).
- [websockets 15.0.1 — BSD-3-Clause](https://github.com/python-websockets/websockets/blob/15.0.1/LICENSE).
