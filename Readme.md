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

## Architektura

Cała logika generowania haseł znajduje się w backendzie PHP (`api.php`). Interfejs (`index.html`)
pobiera hasła z `api.php` przez `fetch`, a mechanizm wykluczeń przechowuje w `localStorage`
przeglądarki (serwer pozostaje bezstanowy).

## Uruchomienie

Aplikacja wymaga serwera z PHP (z rozszerzeniem `mbstring`). Lokalnie najprościej:

```bash
php -S localhost:8000
```

Następnie otwórz `http://localhost:8000/` w przeglądarce.

## API

Backend `api.php` serwuje hasła jako JSON na proste zapytanie `curl` (bez przeglądarki):

```bash
# Pełny JSON ze wszystkimi wariantami
curl https://hasla.zjawa.it/api.php

# Tylko jeden wariant (ascii | long | ascii-short | medium)
curl 'https://hasla.zjawa.it/api.php?variant=long'

# Samo hasło jako czysty tekst (idealne do skryptów)
PASS=$(curl -s 'https://hasla.zjawa.it/api.php?variant=long&format=text')

# Pięć haseł danego wariantu, po jednym na linię
curl 'https://hasla.zjawa.it/api.php?count=5&variant=medium&format=text'
```

### Parametry

| Parametr | Opis |
|----------|------|
| brak | pełny JSON ze wszystkimi czterema wariantami |
| `variant=<klucz>` | jeden wariant: `ascii`, `long`, `ascii-short` lub `medium` |
| `format=text` (alias `raw`) | zwraca samo hasło jako `text/plain`, po jednym na linię |
| `count=N` | liczba haseł (1–20) |

### Wykluczenia (POST)

Aby pominąć wskazane słowa (np. wcześniej użyte), wyślij je w ciele POST jako JSON.
W odpowiedzi każde hasło zawiera pole `words` z użytymi słowami:

```bash
curl -X POST -H 'Content-Type: application/json' \
     -d '{"exclude":["zamek","klucz"]}' \
     https://hasla.zjawa.it/api.php
```

Interfejs webowy korzysta z tego samego mechanizmu — wysyła wykluczenia z `localStorage`
i dopisuje zwrócone słowa, aby hasła nie powtarzały się przez 31 dni.

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
