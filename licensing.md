# Licencja i pochodzenie kodu

Dla źródeł Arkusza przyjęto GNU Affero General Public License version 3, identyfikator SPDX **AGPL-3.0-only**. Pełny, niezmieniony tekst licencji znajduje się w `LICENSE`. Udostępnienie dotyczy praw, którymi udostępniający rzeczywiście może rozporządzać; nie stanowi zapewnienia, że każda część projektu jest objęta ochroną prawnoautorską.

## Pochodzenie kodu

Przy tworzeniu kodu wykorzystano narzędzia generatywnej AI. Nie przesądza to o ochronie prawnoautorskiej poszczególnych elementów. Licencja obejmuje prawa, którymi udostępniający może rozporządzać, i nie tworzy ochrony materiałów, które jej nie podlegają. Nie stanowi też gwarancji wyłącznego autorstwa ani braku podobieństw do innego oprogramowania. Ocena twórczego wkładu człowieka i ochrony zależy od właściwego prawa.

## Zakres AGPLv3

Licencja obejmuje własne źródła całego repozytorium: PHP, HTML, CSS, JavaScript, schemat SQL, migracje, skrypty, testy, dokumentację oraz ikony projektu, w zakresie praw, którymi udostępniający może rozporządzać. Wybór `AGPL-3.0-only` oznacza wersję 3, bez automatycznego wyboru późniejszych wersji. Sama treść pliku `LICENSE` jest dokumentem Free Software Foundation i zachowuje własne oznaczenia.

AGPL pozwala korzystać z programu, modyfikować go, rozpowszechniać oraz używać komercyjnie. Nie dodano zakazu sprzedaży, ograniczenia do użytku niekomercyjnego ani obowiązku oznaczania każdej zmiany jako AI.

Istotna jest sekcja 13: przy uruchamianiu zmodyfikowanej wersji obsługującej interakcję sieciową należy zaoferować jej użytkownikom bezpłatny dostęp do odpowiedniego kodu źródłowego tej wersji. Oferta ma być widoczna, a kod faktycznie dostępny — może to być właściwy stan repozytorium lub archiwum zawierające źródła i potrzebne skrypty budowania oraz instalacji. Samo wskazanie starego wydania nie wystarcza dla zmienionej aplikacji. Obowiązek nie ogranicza się do serwisów publicznych; użytkownikami mogą być również pracownicy łączący się przez sieć.

Źródła programu nie oznaczają danych klientów, zamówień, haseł ani plików sesji. `app_data`, `.env` i kopie bazy nie powinny trafiać do repozytorium ani archiwum źródeł. Szablony konfiguracji i skrypty niezbędne do uruchomienia programu pozostają częścią źródeł.

Bieżące źródła są udostępniane na AGPL-3.0-only. Zmiana licencji nie odbiera uprawnień do kopii wcześniej udostępnionych na innej licencji.

## Komponenty zewnętrzne

Nie dodano bibliotek aplikacyjnych npm ani Composer. Nie kopiowano źródeł PHP, Apache ani MariaDB do aplikacji. Interpretator PHP, serwer HTTP, osobny serwer MariaDB i biblioteki systemowe obrazów pozostają na własnych licencjach. **Nie oznaczamy całej zawartości obrazów kontenerowych jako AGPL** i nie zmieniamy licencji cudzych komponentów.

Przegląd bieżącego kodu i używanych komponentów nie wykazał zależności blokującej AGPL dla Arkusza w obecnej architekturze. Szczegółowy zakres, dowody, ograniczenia i obowiązki przy dystrybucji obrazów zawiera [LICENSE_AUDIT.md](LICENSE_AUDIT.md). Nie jest to opinia prawna ani gwarancja pochodzenia każdej instrukcji wygenerowanego kodu.

## Źródła

- [Warunki korzystania z usług OpenAI w Europie](https://openai.com/policies/eu-terms-of-use/), sekcja dotycząca własności treści.
- [OpenAI Services Agreement](https://openai.com/policies/services-agreement/), prawa klienta do wyniku; zastosowanie zależy od rodzaju konta i umowy.
- [Polska ustawa o prawie autorskim, tekst jednolity Dz.U. 2025 poz. 24](https://eli.gov.pl/api/acts/DU/2025/24/text/I/D20250024.pdf), zwłaszcza art. 1 i 8, z uwzględnieniem późniejszych zmian.
- [Copyright Office: raport o prawnoautorskiej ochronie wyników AI](https://www.copyright.gov/ai/), część 2.
- [GNU AGPLv3](https://www.gnu.org/licenses/agpl-3.0.html), zwłaszcza sekcje 5, 6 i 13.
- [GNU FAQ o interpreterach i licencjach](https://www.gnu.org/licenses/gpl-faq.html#InterpreterIncompat).
- [GNU: dlaczego Affero GPL](https://www.gnu.org/licenses/why-affero-gpl.html).

## Oferta źródeł

Oferta źródeł działającej wersji powinna wskazywać odpowiadający jej commit albo archiwum wydania. Ruchoma gałąź repozytorium może zawierać inne zmiany niż wdrożona aplikacja.
