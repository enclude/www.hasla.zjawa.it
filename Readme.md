# Generator haseł

Generator bezpiecznych, łatwych do zapamiętania haseł oparty na słowach z polskiej literatury.

## Funkcje

- **4 warianty haseł** o różnych długościach (krótkie, średnie, długie, ASCII)
- **Transkrypcja fonetyczna** każdego hasła (alfabet ICAO po polsku)
- **Losowe źródło danych** — przy każdym generowaniu wybierany jest losowy plik z katalogu `sources/`
- **Inteligentne filtrowanie** — pomijane są linijki z mniej niż 5 słowami lub krótsze niż 24 znaki
- **Losowe ciągi słów** — hasła budowane są z losowych, następujących po sobie słów z tekstu
- **Ignorowanie znaków interpunkcyjnych** — znaki przestankowe są automatycznie usuwane
- **Wersja ASCII** — hasło bez polskich znaków diakrytycznych dla systemów z ograniczeniami

## Pliki danych

Pliki źródłowe znajdują się w katalogu `sources/`:

- `ksiazka1.txt` — Joseph Conrad, "Tajny Agent", tłum. Aniela Zagórska, ISBN 978-83-288-5255-6
- `ksiazka2.txt` — Arthur Conan Doyle, "Człowiek z blizną", ISBN 978-83-288-7978-2
- `ksiazka3.txt` — Eliza Orzeszkowa, "Nad Niemnem" tom I, ISBN 978-83-288-2574-1
- `ksiazka4.txt` — Eliza Orzeszkowa, "Nad Niemnem" tom II, ISBN 978-83-288-2573-4
- `ksiazka5.txt` — Eliza Orzeszkowa, "Nad Niemnem" tom III, ISBN 978-83-288-2575-8

Wszystkie książki pochodzą ze strony WolneLektury.pl i są otwartoźródłowe.

## Uruchomienie

Otwórz plik `index.html` w przeglądarce. Strona działa w pełni lokalnie (wymaga serwera HTTP do pobierania plików tekstowych).

## Repozytorium

Repozytorium tej strony jest dostępne tutaj: https://github.com/enclude/www.hasla.zjawa.it

## Strona

Strona jest dostępna pod adresem: https://hasla.zjawa.it

## Licencja

CC BY 4.0 — wymagane wskazanie źródła i twórcy.

## Twórca

Jarosław Zjawiński — kontakt@zjawa.it
