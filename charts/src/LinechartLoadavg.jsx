import { useEffect, useRef } from "react";
import Chart from "chart.js/auto";

function LinechartLoadavg() {
  const chartRef = useRef(null);
  const chartInstance = useRef(null);

  useEffect(() => {
    const sarData = window.sysSnapSarLoadavgData || [];
    // Only keep those with valid Time and ldavg columns
    const filtered = sarData.filter(
      row => row["Time"] && row["ldavg-1"] !== undefined && row["ldavg-5"] !== undefined && row["ldavg-15"] !== undefined
    );

    const labels = filtered.map(row => row["Time"]);
    const dataset1 = filtered.map(row => parseFloat(row["ldavg-1"]));
    const dataset5 = filtered.map(row => parseFloat(row["ldavg-5"]));
    const dataset15 = filtered.map(row => parseFloat(row["ldavg-15"]));

    if (chartInstance.current) {
      chartInstance.current.destroy();
    }

    chartInstance.current = new Chart(chartRef.current, {
      type: "line",
      data: {
        labels,
        datasets: [
          {
            label: "ldavg-1",
            data: dataset1,
            borderColor: "#36a2eb",
            backgroundColor: "rgba(54,162,235,0.1)",
            tension: 0.2,
            fill: false,
          },
          {
            label: "ldavg-5",
            data: dataset5,
            borderColor: "#ff6384",
            backgroundColor: "rgba(255,99,132,0.1)",
            tension: 0.2,
            fill: false,
          },
          {
            label: "ldavg-15",
            data: dataset15,
            borderColor: "#ffce56",
            backgroundColor: "rgba(255,206,86,0.1)",
            tension: 0.2,
            fill: false,
          },
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "top" },
          title: {
            display: true,
            text: "Load Average Over 24 Hours"
          }
        },
        scales: {
          x: {
            title: { display: true, text: "Time" },
            ticks: { autoSkip: true, maxTicksLimit: 15 }
          },
          y: {
            title: { display: true, text: "Load Average" },
            beginAtZero: true
          }
        }
      }
    });

    return () => chartInstance.current?.destroy();
  }, []);

  return (
    <div>
      <canvas ref={chartRef} />
    </div>
  );
}

export default LinechartLoadavg;