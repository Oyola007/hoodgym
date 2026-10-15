// HOOD GYM Interactivity Script

// Animate statistic cards on hover
document.querySelectorAll('.dashboard-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    card.style.transform = 'scale(1.05)';
  });
  card.addEventListener('mouseleave', () => {
    card.style.transform = 'scale(1)';
  });
});

// Dynamic chart using Chart.js (you’ll need to include Chart.js via CDN)
const ctx = document.createElement('canvas');
ctx.id = "workoutChart";
document.querySelector('.chart').appendChild(ctx);

const workoutChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
    datasets: [
      {
        label: 'Calories Burned',
        data: [400, 500, 450, 600, 700, 650, 800],
        borderColor: '#e63946',
        fill: false,
        tension: 0.3
      },
      {
        label: 'Workout Hours',
        data: [1, 1.5, 1, 2, 2.5, 2, 3],
        borderColor: '#4cc9f0',
        fill: false,
        tension: 0.3
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        labels: { color: '#fff' }
      }
    },
    scales: {
      x: { ticks: { color: '#fff' } },
      y: { ticks: { color: '#fff' } }
    }
  }
});

// Smooth scroll for navigation
document.querySelectorAll('nav a').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const section = document.querySelector(link.getAttribute('href'));
    if(section) {
      section.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

// Button click animations
document.querySelectorAll('button').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.style.transform = 'scale(0.95)';
    setTimeout(() => btn.style.transform = 'scale(1)', 150);
  });
});
