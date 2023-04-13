# Versandkosten Beez Plugin

## Voraussetzungen
- Wordpress 5.0 oder höher
- Woocommerce 3.6 oder höher
- PHP 7.2 oder höher

## Installation
Um das Plugin zu installieren, muss es im Plugin-Verzeichnis von Wordpress installiert werden. Dazu muss das Plugin heruntergeladen werden und im Plugin-Verzeichnis von Wordpress entpackt werden. Danach muss das Plugin im Wordpress-Backend aktiviert werden.

## Konfiguration
Nach der Installation muss das Plugin noch konfiguriert werden.

### Parameter für die Berechnung der Versandkosten
Um die Versandkosten-Berechnung zu aktivieren, muss im Menü "Woocommerce" > "Einstellungen" > "Versand" > "Versandkosten Beez Einstellungen" aufgerufen werden. Hier müssen alle Felder ausgefüllt werden und die Versandkosten über das Feld "Versandkosten aktivieren" aktiviert werden.

### Einstellung der Lieferkapazitäten
Um die Lieferkapazitäten zu konfigurieren, muss im Menü "Versandkosten Beez Lieferwochen Management" aufgerufen werden. Hier werden die Lieferkapazitäten für die einzelnen Lieferwochen angezeigt. Standardmäßig sind die Lieferkapazitäten auf 0 gesetzt. Um die Änderungen zu Speichern muss der Speichern-Button angeklickt werden.


## Verwendete externe Schnittstellen und Lizenzen

### GeoNames
Für die Validierung der Postleitzahlen wurde die Schnittstelle von [GeoNames](http://www.geonames.org/) verwendet. Hierbei wird die Postleitzahl an die Schnittstelle übergeben und geprüft, ob die Postleitzahl existiert. Dabei mitgelieferte Daten sind die Stadt sowie die Region und werden im Woocommerce Profil des Kunden gespeichert.

Hinweis: Es sind Premium-Zugänge möglich, die mehr Daten liefern und eine höhere Verfügbarkeit haben. Diese werden jedoch nicht verwendet.

- Lizenz: [Creative Commons Attribution 3.0 License](https://creativecommons.org/licenses/by/3.0/)
- Es muss angegeben werden, dass die Daten an [GeoNames](http://www.geonames.org/) gesendet werden und dass die Daten von **GeoNames** verwendet werden.

### Google Maps Distance Matrix API
Für die Berechnung der Entfernung zum Lieferort wurde die Schnittstelle von [Google Maps Distance Matrix API](https://developers.google.com/maps/documentation/distance-matrix/intro) verwendet. Hierbei wird die Entfernung zwischen dem Betrieb und den Lieferort in Kilometern und Stunden berechnet. Für die Berechnung müssen die Postleitzahlen des Betriebes und des Kunden übergeben werden.

- Lizenz: [Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0)
- Es muss angegeben werden, dass die Daten an [Google Maps Distance Matrix API](https://developers.google.com/maps/documentation/distance-matrix/intro) gesendet werden und dass die Daten von der **Google Maps Distance Matrix API** verwendet werden.


## Developer
Dieses Plugin wurde von der [Medienwerkstatt-Niederrhein](https://medienwerkstatt-niederrhein.de/) entwickelt.