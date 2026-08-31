# Geo catalog

Compact country / state / city JSON used by admin geo-blocking.

- `countries.json` — ISO2, name, flag
- `states.json` — keyed by country ISO2
- `cities/{ISO2}.json` — keyed by state ISO2, city name arrays

Source: [country-state-city](https://github.com/harpreetkhalsagtbit/country-state-city) 3.2.1 (names and ISO codes only).
