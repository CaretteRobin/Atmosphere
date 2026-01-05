# Atmosphere (Interop)

Page PHP dynamique qui combine geolocalisation IP, meteo XML + XSL, trafic Grand Nancy, signal SRAS dans les eaux usees et qualite de l'air pour indiquer si prendre la voiture est pertinent.

## Structure
- `Interop/atmosphere.php` : page principale.
- `Interop/css/style.css` : mise en forme responsive (mobile / tablette / desktop).
- `Interop/js/charts.js` : init Chart.js et Leaflet.
- `Interop/xsl/meteo.xsl` : transformation XSLT du fragment meteo.
- `Interop/README.md` : ce fichier.

## Choix des API et ecarts par rapport aux TP
- L'enonce demande d'utiliser les APIs vues en TP; certaines sont devenues instables (HTTP-only, flux arrete, 301) et ne tiennent pas sur webetu.
- Pour garder l'objectif pedagogique (interop, formats, robustesse) la page remplace les sources fragiles par des equivalents open data documentes; chaque ecart est justifie ci-dessous.

| Besoin | API TP / proposee | Probleme constate | API retenue | Pourquoi ce choix |
|---|---|---|---|---|
| Geolocalisation IP (XML) | `ip-api.com` / `freegeoip.net` | HTTP-only ou service change/instable | `https://ipapi.co/{IP}/xml/` | HTTPS, XML stable, champs lat/lon conformes |
| Meteo | flux XML TP (Nancy) | besoin d'une source stable | Open-Meteo JSON + XML interne + XSL (3 blocs) | Source fiable, et contrainte XSL respectee via conversion |
| Covid / SRAS | chiffres Covid classiques | mesures arretées depuis 06/2023 | SUM'eau ODISSE `sum-eau-indicateurs` | Conforme consigne “SRAS eaux usees” |
| Circulation | `carto.g-ny...` | redirection HTTP 301 selon contexte | `https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json` (via cURL follow redirect) | Meme source, mais suivie proprement pour eviter les 301 |

## APIs utilisees (urls affichees sur la page)
- Geolocalisation IP : `https://ipapi.co/{IP}/xml/`.
- Geocodage (IUT + marqueurs) : `https://api-adresse.data.gouv.fr/search/?q=<adresse>`.
- Meteo (Open-Meteo → XML interne → XSL) : `https://api.open-meteo.com/v1/forecast?latitude=<lat>&longitude=<lon>&hourly=temperature_2m,precipitation,wind_speed_10m,weathercode&forecast_days=1&timezone=Europe/Paris`.
- Trafic / perturbations Grand Nancy : `https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json` (cURL suit la redirection eventuelle `carto.g-ny.org`).
- SRAS eaux usees (SUM'eau) : `https://odisse.santepubliquefrance.fr/api/explore/v2.1/catalog/datasets/sum-eau-indicateurs/records?limit=20&order_by=semaine%20desc&where=station%20like%20%22%25%25NANCY%25%25%22` (puis Maxeville/commune Nancy en repli).
- Qualite de l'air : `https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27NANCY%27&outFields=*&returnGeometry=false&f=pjson`.

Les liens ci-dessus sont aussi affiches en bas de `Interop/atmosphere.php` (sources cliquables).

## Deploiement webetu
1. Copier le dossier `Interop/` sur votre espace `~/public_html` webetu.
2. Verifier les droits en lecture sur les fichiers (`chmod 644` suffisant).
3. Tester via `https://webetu.iutnc.univ-lorraine.fr/~votrelogin/Interop/atmosphere.php`.

## Points techniques
- Requetes externes via cURL (FOLLOWLOCATION) + proxy impose webetu (`tcp://127.0.0.1:8080`) avec repli direct si le proxy echoue.
- Meteo : Open-Meteo JSON est converti en XML (matin/midi/soir) puis transforme en HTML par `XSLTProcessor` et `xsl/meteo.xsl`. DTD minimale incluse.
- Robustesse : controle du code HTTP, parsing (JSON/XML), fallbacks affiches avec messages utilisateur en cas d'echec API.
- Leaflet et Chart.js charges depuis CDN; la carte ajoute un marqueur IP + IUT Charlemagne + Gare de Nancy.

## Robustesse / contraintes webetu
- Proxy webetu pris en charge (cURL proxy 127.0.0.1:8080, repli direct si indisponible).
- Erreurs API gerees : message utilisateur + donnees de secours pour garder la page lisible.
- Suivi des redirections (FOLLOWLOCATION) pour les flux trafic (301 frequents).
- Date/heure de mise a jour affichee dans `atmosphere.php` quand les donnees sont presentes.

## A adapter
- Renseigner le lien du depot public dans `Interop/atmosphere.php` (`$repoLink`).
- Ajuster les urls API si l'open data change ou si un autre dataset est prefere en TP.
