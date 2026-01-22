# Generator haseł

Generator bezpiecznych, łatwych do zapamiętania haseł oparty na słowach z polskiej literatury.

## Funkcje

- **4 warianty haseł** o różnych długościach (krótkie, średnie, długie, ASCII)
- **Transkrypcja fonetyczna** każdego hasła (alfabet ICAO po polsku)
- **Losowe źródło danych** — przy każdym generowaniu wybierany jest losowy plik z katalogu `sources/`
- **Inteligentne filtrowanie** — pomijane są linijki z mniej niż 5 słowami lub krótsze niż 24 znaki
- **Losowe ciągi słów** — hasła budowane są z losowych, następujących po sobie słów z tekstu
- **Ignorowanie znaków interpunkcyjnych** — znaki przestankowe są automatycznie usuwane
- **Wersja ASCII** — hasło bez polskich znaków diakrytycznych dla systemów z ograniczeniami (minimum 3 słowa)
- **Mechanizm wykluzji** — używane słowa są wykluczane przez 31 dni, aby zwiększyć niepowtarzalność haseł
- **Przycisk "Wyczyść historię"** — ręczne wyczyszczenie listy wykluczonych słów

## Pliki danych i źródło

Pliki źródłowe znajdują się w katalogu `sources/`

Wszystkie książki pochodzą ze strony [WolneLektury.pl](https://wolnelektury.pl) i są otwartoźródłowe.

## Uruchomienie

Otwórz plik `index.html` w przeglądarce. Strona działa w pełni lokalnie (wymaga serwera HTTP do pobierania plików tekstowych).

## Techniczne

- **localStorage** — wykluczone słowa przechowywane lokalnie w przeglądarce użytkownika
- **Maksymalna liczba wykluzji** — 1500 słów (automatyczne usuwanie najstarszych)
- **Czyszczenie** — przeterminowane wpisy są automatycznie usuwane

## Repozytorium

Repozytorium tej strony jest dostępne tutaj: https://github.com/enclude/www.hasla.zjawa.it

## Strona

Strona jest dostępna pod adresem: https://hasla.zjawa.it

## Licencja

CC BY 4.0 — wymagane wskazanie źródła i twórcy.

## Twórca

Jarosław Zjawiński — kontakt@zjawa.it
