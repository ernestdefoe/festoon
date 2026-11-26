/*
 * Fireworks over the welcome banner.
 *
 * Rockets climb with a fading trail and burst at the top of their arc. Sparks
 * fall under gravity with air drag and flicker as they die; bursts come as
 * peonies, rings, slow gold willows and crackles. They are drawn with additive
 * blending on the banner's night sky, so where sparks cross they glow brighter,
 * the way real ones do.
 *
 * The show runs only while the banner is on screen and the tab is visible,
 * and the canvas is capped at 2× pixel density.
 */

interface Rocket {
  x: number;
  y: number;
  vx: number;
  vy: number;
  trail: [number, number][];
  color: string;
  kind: 'peony' | 'ring' | 'willow' | 'crackle';
}

interface Spark {
  x: number;
  y: number;
  px: number;
  py: number;
  vx: number;
  vy: number;
  drag: number;
  life: number;
  max: number;
  color: string;
  crackle: boolean;
  size: number;
  flash?: boolean;
}

const G = 0.045;
const KINDS: Rocket['kind'][] = ['peony', 'peony', 'ring', 'willow', 'crackle'];
const rand = (a: number, b: number) => a + Math.random() * (b - a);
const pick = <T>(a: T[]): T => a[Math.floor(Math.random() * a.length)];

/** A burst's sparks, plus one white flash at its centre. */
function burst(r: Rocket, colors: string[]): Spark[] {
  const n = r.kind === 'ring' ? 48 : r.kind === 'willow' ? 70 : rand(80, 110);
  const speed = r.kind === 'willow' ? 2.2 : rand(2.4, 3.4);
  const second = Math.random() < 0.35 ? pick(colors) : null;
  const out: Spark[] = [];

  for (let i = 0; i < n; i++) {
    const a = r.kind === 'ring' ? (i / n) * Math.PI * 2 : Math.random() * Math.PI * 2;
    // cube root spreads sparks evenly through the sphere, not bunched at its middle
    const s = r.kind === 'ring' ? speed : speed * Math.cbrt(Math.random());
    out.push({
      x: r.x,
      y: r.y,
      px: r.x,
      py: r.y,
      vx: Math.cos(a) * s,
      vy: Math.sin(a) * s,
      drag: r.kind === 'willow' ? 0.955 : 0.965,
      life: 0,
      max: r.kind === 'willow' ? rand(110, 150) : rand(55, 85),
      color: r.kind === 'willow' ? '#ffc870' : second && i % 2 ? second : r.color,
      crackle: r.kind === 'crackle',
      size: r.kind === 'willow' ? 1.3 : 1.7,
    });
  }
  out.push({ flash: true, x: r.x, y: r.y, px: 0, py: 0, vx: 0, vy: 0, drag: 0, life: 0, max: 8, color: '#fff', crackle: false, size: 0 });

  return out;
}

/** Moves and draws a rocket with its trail; false once it reaches the top and bursts. */
function climb(cx: CanvasRenderingContext2D, r: Rocket, dt: number): boolean {
  r.trail.push([r.x, r.y]);
  if (r.trail.length > 10) r.trail.shift();
  r.x += r.vx * dt;
  r.y += r.vy * dt;
  r.vy += G * dt;
  cx.fillStyle = '#ffe0a0';
  r.trail.forEach(([x, y], i) => {
    cx.globalAlpha = (i / r.trail.length) * 0.6;
    cx.fillRect(x - 0.8, y - 0.8, 1.6, 1.6);
  });

  return r.vy < -0.3;
}

/** Moves and draws a spark; false once it has burned out. */
function fall(cx: CanvasRenderingContext2D, s: Spark, dt: number): boolean {
  s.life += dt;
  const k = 1 - s.life / s.max;
  if (k <= 0) return false;

  if (s.flash) {
    cx.globalAlpha = k * 0.5;
    const g = cx.createRadialGradient(s.x, s.y, 0, s.x, s.y, 40);
    g.addColorStop(0, '#fff');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    cx.fillStyle = g;
    cx.fillRect(s.x - 40, s.y - 40, 80, 80);
    return true;
  }

  s.px = s.x;
  s.py = s.y;
  s.vx *= Math.pow(s.drag, dt);
  s.vy = s.vy * Math.pow(s.drag, dt) + G * 0.55 * dt;
  s.x += s.vx * dt;
  s.y += s.vy * dt;

  let a = k < 0.35 ? k / 0.35 : 1;
  if (k < 0.45 && Math.random() < 0.3) a *= 0.2; // flicker as it dies

  if (s.crackle && k < 0.25) {
    cx.globalAlpha = Math.random();
    cx.fillStyle = '#fff';
    cx.fillRect(s.x + rand(-3, 3), s.y + rand(-3, 3), 1.2, 1.2);
  }

  cx.globalAlpha = a;
  cx.strokeStyle = s.color;
  cx.lineWidth = s.size;
  cx.lineCap = 'round';
  cx.beginPath();
  cx.moveTo(s.px - s.vx * 2, s.py - s.vy * 2);
  cx.lineTo(s.x, s.y);
  cx.stroke();
  return true;
}

class Show {
  canvas = document.createElement('canvas');
  cx = this.canvas.getContext('2d')!;
  w = 0;
  h = 0;
  raf = 0;
  last = 0;
  nextLaunch = 0;
  onScreen = true;
  rockets: Rocket[] = [];
  sparks: Spark[] = [];
  resize = new ResizeObserver(() => this.fit());
  seen = new IntersectionObserver(([entry]) => {
    this.onScreen = entry.isIntersecting;
    this.onScreen ? this.run() : this.pause();
  });
  visibility = () => (document.hidden ? this.pause() : this.run());

  constructor(
    hero: HTMLElement,
    private colors: string[]
  ) {
    this.canvas.className = 'Festoon-sky';
    this.canvas.setAttribute('aria-hidden', 'true');
    hero.prepend(this.canvas);
    this.resize.observe(this.canvas);
    this.seen.observe(hero);
    document.addEventListener('visibilitychange', this.visibility);
    this.fit();
    this.run();
  }

  fit() {
    const r = Math.min(2, window.devicePixelRatio || 1);
    this.w = this.canvas.clientWidth;
    this.h = this.canvas.clientHeight;
    this.canvas.width = this.w * r;
    this.canvas.height = this.h * r;
    this.cx.setTransform(r, 0, 0, r, 0, 0);
  }

  launch() {
    const { w, h } = this;
    const tx = rand(w * 0.12, w * 0.88);
    const ty = rand(h * 0.12, h * 0.42);
    const x = tx + rand(-40, 40);
    const y = h + 4;
    this.rockets.push({ x, y, vx: (tx - x) / 55, vy: -Math.sqrt(2 * G * (y - ty)), trail: [], color: pick(this.colors), kind: pick(KINDS) });
  }

  frame = (t: number) => {
    const cx = this.cx;
    this.raf = requestAnimationFrame(this.frame);
    const dt = Math.min(2, (t - this.last) / 16.67 || 1);
    this.last = t;

    if (t > this.nextLaunch) {
      this.launch();
      if (Math.random() < 0.3) this.launch();
      this.nextLaunch = t + rand(700, 1600);
    }

    cx.globalCompositeOperation = 'source-over';
    cx.clearRect(0, 0, this.w, this.h);
    cx.globalCompositeOperation = 'lighter';

    this.rockets = this.rockets.filter((r) => {
      if (climb(cx, r, dt)) return true;
      this.sparks.push(...burst(r, this.colors));
      return false;
    });
    this.sparks = this.sparks.filter((s) => fall(cx, s, dt));
    cx.globalAlpha = 1;
  };

  run() {
    if (this.raf || !this.onScreen || document.hidden) return;
    this.last = performance.now();
    this.raf = requestAnimationFrame(this.frame);
  }

  pause() {
    cancelAnimationFrame(this.raf);
    this.raf = 0;
  }

  stop() {
    this.pause();
    this.resize.disconnect();
    this.seen.disconnect();
    document.removeEventListener('visibilitychange', this.visibility);
    this.canvas.remove();
  }
}

export default function show(hero: HTMLElement, colors: string[]): () => void {
  const s = new Show(hero, colors);

  return () => s.stop();
}
