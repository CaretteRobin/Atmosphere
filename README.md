# Circulations – Interop (DWM)

Projet du module **Interopérabilité** consistant à réaliser une page web asynchrone croisant plusieurs **API vues en TP**, afin d’aider à décider s’il est pertinent d’utiliser la voiture en ville.

La page combine des données de géolocalisation, météo, trafic, qualité de l’air et signal sanitaire (SRAS dans les eaux usées).

---

## Structure du projet

- `Interop/atmosphere.php` : page principale (PHP + fetch JS)
- `Interop/css/style.css` : mise en forme responsive (desktop / mobile)
- `Interop/js/charts.js` : graphiques (Chart.js) et carte (Leaflet)
- `Interop/xsl/meteo.xsl` : transformation XSLT des données météo
- `Interop/README.md` : documentation

---

## Fonctionnalités

- Géolocalisation IP du client
- Affichage de la météo locale (matin / midi / soir)
- Carte Leaflet centrée sur la position du client
- Affichage des perturbations de trafic dans le Grand Nancy
- Indicateur de qualité de l’air
- Courbe de tendance SRAS (eaux usées)
- Synthèse finale indiquant si l’usage de la voiture est conseillé ou non

Toutes les données sont chargées **de manière asynchrone**.

---

## APIs utilisées (issues des TP)

Les liens exacts utilisés sont affichés sur la page web, conformément aux consignes.

### Géolocalisation IP
- `https://ipapi.co/{IP}/xml/`

### Géocodage des adresses (repères)
- `https://api-adresse.data.gouv.fr/search/?q=<adresse>`

### Météo
- `https://api.open-meteo.com/v1/forecast?latitude=<lat>&longitude=<lon>&hourly=temperature_2m,precipitation,wind_speed_10m,weathercode&forecast_days=1&timezone=Europe/Paris`

Les données météo sont récupérées en JSON, converties en XML puis transformées en HTML à l’aide de **XSLT**.

### Trafic – Grand Nancy
- `https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json`

### Qualité de l’air
- API ArcGIS Grand Est  
- Requête POST :

```
https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query
```

### SRAS dans les eaux usées (SUM’eau – Santé publique France)
- `https://odisse.santepubliquefrance.fr/api/explore/v2.1/catalog/datasets/sum-eau-indicateurs/records`

Requêtes utilisées pour la commune de **Nancy** (ordre ascendant et descendant) afin de construire la courbe de tendance.

---

## Contraintes respectées

- Utilisation exclusive d’APIs vues en TP
- Appels asynchrones avec `fetch`
- Gestion des erreurs et affichage de messages de secours
- Affichage des dates de mise à jour quand disponibles
- Liens vers toutes les ressources utilisées
- Mise en forme responsive (projet DWM)

---

## Déploiement

Le projet est disponible sur **webetu** dans le répertoire `Interop`, sous le fichier :

`atmosphere.php`

---

## Dépôt Git

Le code source est disponible publiquement à l’adresse suivante :

https://github.com/CaretteRobin/Atmosphere.git
