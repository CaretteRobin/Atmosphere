(function () {
  const data = window.atmoData || {};
  let covidChart = null;

  function buildChart() {
    const canvas = document.getElementById("covidChart");
    if (
      !canvas ||
      !window.Chart ||
      !data.wastewater ||
      !data.wastewater.ok ||
      !data.wastewater.values ||
      data.wastewater.values.length === 0
    ) {
      return;
    }
    if (covidChart) {
        covidChart.destroy();
        covidChart = null;
    }
    const ctx = canvas.getContext("2d");
    const labels = data.wastewater.labels || [];
    const values = (data.wastewater.values || []).map((v) => Number(v));
    covidChart = new Chart(ctx, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "Signal SRAS (eaux usees)",
            data: values,
            tension: 0.25,
            borderColor: "#3fd0c9",
            backgroundColor: "rgba(63, 208, 201, 0.15)",
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        resizeDelay: 150,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { display: true },
          tooltip: { mode: "index", intersect: false }
        },
        scales: {
          x: {
            ticks: { color: "#8ca0b3" },
            grid: { display: false },
            title: { display: false }
          },
          y: {
            ticks: {
              color: "#8ca0b3",
              callback: (value) => value.toLocaleString("fr-FR")
            },
            grid: { color: "rgba(255,255,255,0.05)" },
            beginAtZero: true
          }
        }
      }
    });
  }

  function buildMap() {
    const mapEl = document.getElementById("map");
    if (!mapEl || !window.L) {
      return;
    }
    const center = data.mapCenter || { lat: 48.6921, lon: 6.1844 };
    const map = L.map(mapEl, { scrollWheelZoom: false }).setView([center.lat, center.lon], 12);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap"
    }).addTo(map);

    (data.markers || []).forEach((m) => {
      L.marker([m.lat, m.lon]).addTo(map).bindPopup(m.label || "Repere");
    });

    (data.traffic || []).forEach((item) => {
      const popup = `<strong>${item.title || "Perturbation"}</strong><br>${item.description || ""}<br>${item.start || ""} - ${item.end || ""}`;
      L.circleMarker([item.lat, item.lon], {
        radius: 6,
        color: "#f0b429",
        fillColor: "#f0b429",
        fillOpacity: 0.75
      }).addTo(map).bindPopup(popup);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    buildChart();
    buildMap();
  });
})();
