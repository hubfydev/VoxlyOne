/* Confetti da nota 10 — canvas próprio, sem CDN nem dependência. */

export function celebrate(durationMs = 2200) {
  const canvas = document.createElement('canvas');
  canvas.className = 'confetti';
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  document.body.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  const colors = ['#1d4ed8', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed'];
  const pieces = Array.from({ length: 120 }, () => ({
    x: Math.random() * canvas.width,
    y: -20 - Math.random() * canvas.height * 0.5,
    size: 6 + Math.random() * 6,
    speed: 2 + Math.random() * 3,
    drift: -1 + Math.random() * 2,
    spin: -0.2 + Math.random() * 0.4,
    angle: Math.random() * Math.PI,
    color: colors[Math.floor(Math.random() * colors.length)],
  }));

  const start = performance.now();

  (function frame(now) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (const p of pieces) {
      p.y += p.speed;
      p.x += p.drift;
      p.angle += p.spin;

      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.angle);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
      ctx.restore();
    }

    if (now - start < durationMs) {
      requestAnimationFrame(frame);
    } else {
      canvas.remove();
    }
  })(start);
}
