/* AOT IDLE — moteur d'effets : rendu des combattants, impacts, particules,
   secousses, bannières et son. Tout est synthétisé ou dessiné à la volée :
   aucun fichier audio, aucune dépendance.

   Le jeu appelle `Fx.hit()`, `Fx.spawn()`, `Fx.kill()`… et laisse ce fichier
   décider de ce qui se voit. L'option « Animations réduites » coupe les
   mouvements sans jamais couper l'information (barres, nombres, bannières
   restent lisibles). */
(function () {
  'use strict';

  /* `fps` : 0 = sans limite, sinon 30 ou 60 images par seconde. Limiter le
     rendu soulage la batterie et les téléphones modestes sans jamais toucher à
     la simulation, qui reste calculée en temps réel. */
  var prefs = {
    sound: true,
    motion: !matchMedia('(prefers-reduced-motion: reduce)').matches,
    fps: 0,
    meter: false,
    music: true
  };
  try { Object.assign(prefs, JSON.parse(localStorage.getItem('aot-v3-settings') || '{}')); } catch (e) { /* ignoré */ }

  function savePrefs() {
    try { localStorage.setItem('aot-v3-settings', JSON.stringify(prefs)); } catch (e) { /* ignoré */ }
    document.body.classList.toggle('reduced', !prefs.motion);
    applyMood(true);
  }

  // ------------------------------------------------------------------
  // Son : synthèse Web Audio
  // ------------------------------------------------------------------

  var audio = null;
  var noiseBuffer = null;
  var lastSound = 0;
  var api_mood = function () {};

  function ensureAudio() {
    if (!audio) {
      try { audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; }
    }
    if (audio.state === 'suspended') audio.resume();
    if (!noiseBuffer) {
      noiseBuffer = audio.createBuffer(1, audio.sampleRate * 0.4, audio.sampleRate);
      var data = noiseBuffer.getChannelData(0);
      for (var i = 0; i < data.length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / data.length);
    }
    return audio;
  }

  document.addEventListener('pointerdown', function () {
    ensureAudio();
    buildAmbience();
  }, { passive: true });
  document.addEventListener('visibilitychange', function () { applyMood(true); });

  function tone(type, freq, target, gain, dur, delay) {
    var t = audio.currentTime + (delay || 0);
    var osc = audio.createOscillator();
    var vol = audio.createGain();
    osc.connect(vol); vol.connect(audio.destination);
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    osc.frequency.exponentialRampToValueAtTime(Math.max(20, target), t + dur);
    vol.gain.setValueAtTime(gain, t);
    vol.gain.exponentialRampToValueAtTime(0.0008, t + dur);
    osc.start(t); osc.stop(t + dur + 0.02);
  }

  function noise(gain, dur, cutoff, delay) {
    var t = audio.currentTime + (delay || 0);
    var src = audio.createBufferSource();
    var filter = audio.createBiquadFilter();
    var vol = audio.createGain();
    src.buffer = noiseBuffer;
    filter.type = 'lowpass';
    filter.frequency.setValueAtTime(cutoff, t);
    filter.frequency.exponentialRampToValueAtTime(cutoff * 0.25, t + dur);
    src.connect(filter); filter.connect(vol); vol.connect(audio.destination);
    vol.gain.setValueAtTime(gain, t);
    vol.gain.exponentialRampToValueAtTime(0.0008, t + dur);
    src.start(t); src.stop(t + dur + 0.02);
  }

  /* ---------------------------------------------------------------- */
  /* Ambiance : une nappe jouée en direct, pas un fichier.               */
  /* ---------------------------------------------------------------- */

  /* Trois oscillateurs très graves, un souffle filtré et un balayage lent
     suffisent à poser une atmosphère. C'est de la musique générée : elle ne
     pèse rien dans l'APK, ne boucle jamais tout à fait pareil, et se réaccorde
     quand on change d'écran. */
  var MOODS = {
    qg: { root: 82.4, fifth: 1.5, cutoff: 620, gain: 0.05 },        // mi grave, calme
    campagne: { root: 73.4, fifth: 1.5, cutoff: 520, gain: 0.055 }, // ré, tension sourde
    boss: { root: 55.0, fifth: 1.414, cutoff: 380, gain: 0.075 },   // la grave, quinte diminuée
    guerre: { root: 61.7, fifth: 1.335, cutoff: 440, gain: 0.065 }, // si bémol, dissonant
    arene: { root: 98.0, fifth: 1.5, cutoff: 700, gain: 0.05 }      // sol, plus ouvert
  };

  var ambience = null;
  var mood = 'qg';

  function buildAmbience() {
    if (ambience || !ensureAudio()) return;
    var mix = audio.createGain();
    var filter = audio.createBiquadFilter();
    filter.type = 'lowpass';
    filter.Q.value = 0.8;
    mix.gain.value = 0;
    filter.connect(mix);
    mix.connect(audio.destination);

    var voices = [audio.createOscillator(), audio.createOscillator(), audio.createOscillator()];
    voices[0].type = 'sine';
    voices[1].type = 'triangle';
    voices[2].type = 'sine';
    voices.forEach(function (osc) {
      var gain = audio.createGain();
      gain.gain.value = 0.34;
      osc.connect(gain);
      gain.connect(filter);
      osc.start();
    });

    // Souffle : le buffer de bruit relu en boucle, très filtré.
    var breath = audio.createBufferSource();
    var breathGain = audio.createGain();
    breath.buffer = noiseBuffer;
    breath.loop = true;
    breathGain.gain.value = 0.05;
    breath.connect(breathGain);
    breathGain.connect(filter);
    breath.start();

    // Balayage lent du filtre : l'ambiance respire au lieu de bourdonner.
    var lfo = audio.createOscillator();
    var lfoGain = audio.createGain();
    lfo.frequency.value = 0.06;
    lfoGain.gain.value = 140;
    lfo.connect(lfoGain);
    lfoGain.connect(filter.frequency);
    lfo.start();

    ambience = { mix: mix, filter: filter, voices: voices };
    applyMood(true);
  }

  function applyMood(immediate) {
    if (!ambience || !audio) return;
    var recipe = MOODS[mood] || MOODS.qg;
    var t = audio.currentTime;
    var slide = immediate ? 0.2 : 2.5;
    var target = prefs.music && !document.hidden ? recipe.gain : 0;
    ambience.mix.gain.cancelScheduledValues(t);
    ambience.mix.gain.setTargetAtTime(target, t, immediate ? 0.1 : 1.2);
    ambience.filter.frequency.setTargetAtTime(recipe.cutoff, t, slide / 2);
    var tuning = [recipe.root, recipe.root * recipe.fifth, recipe.root * 2.01];
    ambience.voices.forEach(function (osc, i) {
      osc.frequency.setTargetAtTime(tuning[i], t, slide / 2);
    });
  }

  api_mood = function (name) {
    if (!MOODS[name] || mood === name) return;
    mood = name;
    applyMood(false);
  };

  /* Chaque son est une petite recette : une lame siffle et claque, un boss
     gronde, une montée de niveau monte un accord. */
  function sound(kind) {
    if (!prefs.sound || document.hidden || !ensureAudio()) return;
    var t = audio.currentTime;
    if ((kind === 'hit' || kind === 'tap') && t - lastSound < 0.05) return;
    lastSound = t;
    switch (kind) {
      case 'hit': noise(0.05, 0.12, 1800); tone('sawtooth', 190, 60, 0.03, 0.1); break;
      case 'tap': noise(0.035, 0.08, 2600); break;
      case 'crit': noise(0.09, 0.22, 3200); tone('square', 880, 220, 0.05, 0.2); break;
      case 'skill': tone('triangle', 520, 1180, 0.07, 0.22); noise(0.05, 0.18, 2400); break;
      case 'heal': tone('sine', 420, 760, 0.07, 0.35); tone('sine', 630, 940, 0.04, 0.35, 0.06); break;
      case 'win': tone('triangle', 520, 660, 0.07, 0.18); tone('triangle', 780, 1040, 0.06, 0.3, 0.1); break;
      case 'loot': tone('sine', 900, 1500, 0.05, 0.16); tone('sine', 1350, 1800, 0.04, 0.22, 0.07); break;
      case 'boss': tone('sawtooth', 90, 40, 0.09, 0.9); noise(0.07, 0.8, 500); break;
      case 'level': [523, 659, 784, 1046].forEach(function (f, i) { tone('triangle', f, f, 0.05, 0.26, i * 0.07); }); break;
      case 'defeat': tone('sawtooth', 320, 70, 0.07, 0.7); noise(0.05, 0.5, 700); break;
      default: tone('sine', 350, 420, 0.045, 0.12);
    }
  }

  // ------------------------------------------------------------------
  // État visuel
  // ------------------------------------------------------------------

  var enemyView = { recoil: 0, squash: 0, flash: 0, entry: 1, dying: 0, offset: 0 };
  var heroView = { lunge: 0 };
  var particles = [];
  var slashes = [];
  var rings = [];
  var shake = 0;
  var hitStop = 0;
  var parallax = 0;
  var images = {};

  function image(src) {
    if (!images[src]) { images[src] = new Image(); images[src].src = src; }
    return images[src];
  }

  function push(list, item) { list.push(item); if (list.length > 140) list.splice(0, list.length - 140); }

  function burst(x, y, count, color, speed) {
    if (!prefs.motion) return;
    for (var i = 0; i < count; i++) {
      var angle = Math.random() * Math.PI * 2;
      var v = speed * (0.4 + Math.random());
      push(particles, {
        x: x, y: y,
        vx: Math.cos(angle) * v, vy: Math.sin(angle) * v - speed * 0.4,
        life: 1, decay: 0.02 + Math.random() * 0.03,
        size: 2 + Math.random() * 3, color: color, gravity: 0.22
      });
    }
  }

  // ------------------------------------------------------------------
  // Interface appelée par le jeu
  // ------------------------------------------------------------------

  /* Limiteur d'images. Chaque boucle (jeu, effets) a son propre compteur :
     avec un compteur commun, les deux se voleraient les images et le rendu
     tomberait à la moitié du réglage. */
  var gates = {};
  function frameGate(now, key) {
    if (!prefs.fps) return true;
    var name = key || 'default';
    if (now - (gates[name] || 0) < 1000 / prefs.fps - 1) return false;
    gates[name] = now;
    return true;
  }

  /* Compteur d'images, affiché à la demande depuis le profil. */
  var meterFrames = 0;
  var meterSince = 0;
  function meterTick(now) {
    var el = document.getElementById('fps-meter');
    if (!el) return;
    if (!prefs.meter) { el.hidden = true; return; }
    el.hidden = false;
    meterFrames++;
    if (!meterSince) meterSince = now;
    if (now - meterSince >= 500) {
      el.textContent = Math.round(meterFrames * 1000 / (now - meterSince)) + ' i/s';
      meterFrames = 0;
      meterSince = now;
    }
  }

  var api = {};

  api.frameGate = frameGate;
  api.prefs = prefs;
  api.mood = function (name) { api_mood(name); };
  api.startAmbience = function () { buildAmbience(); };
  api.sound = sound;
  api.savePrefs = savePrefs;

  /* Un coup : arrêt sur image très court, recul de la cible, éclats et lame. */
  api.hit = function (crit, ratio) {
    sound(crit ? 'crit' : 'hit');
    if (!prefs.motion) return;
    var force = crit ? 1 : 0.55;
    enemyView.recoil = Math.min(1.6, enemyView.recoil + force);
    enemyView.squash = Math.min(1, enemyView.squash + force * 0.6);
    enemyView.flash = Math.max(enemyView.flash, crit ? 1 : 0.55);
    heroView.lunge = 1;
    hitStop = crit ? 0.07 : 0.03;
    shake = Math.min(9, shake + (crit ? 6 : 2.2) * (1 + (ratio || 0)));
    var angle = -0.9 + Math.random() * 0.5;
    push(slashes, { life: 1, angle: angle, crit: crit, width: crit ? 5 : 3 });
    burst(150, 150, crit ? 22 : 8, crit ? '#ffd27a' : '#cdf5e4', crit ? 6 : 3.5);
  };

  api.tap = function () {
    sound('tap');
    if (!prefs.motion) return;
    enemyView.recoil = Math.min(1.6, enemyView.recoil + 0.25);
    burst(150, 160, 4, '#bde9ff', 2.6);
  };

  /* Apparition : le titan entre par le côté, le boss fait trembler l'écran. */
  api.spawn = function (enemy) {
    enemyView.entry = 1;
    enemyView.dying = 0;
    enemyView.recoil = 0;
    enemyView.flash = 0;
    if (!enemy) return;
    if (enemy.boss) {
      sound('boss');
      shake = 10;
      api.banner(enemy.name, 'BOSS DE CHAPITRE', 'boss');
    } else if (enemy.elite) {
      sound('skill');
      api.banner(enemy.name, 'ÉLITE', 'elite');
    }
  };

  /* Mort : le titan se dissout en vapeur, comme dans la série. */
  api.kill = function () {
    if (!prefs.motion) { enemyView.dying = 0.001; return; }
    enemyView.dying = 1;
    shake = Math.min(12, shake + 4);
    for (var i = 0; i < 16; i++) {
      push(particles, {
        x: 110 + Math.random() * 80, y: 90 + Math.random() * 120,
        vx: (Math.random() - 0.5) * 1.4, vy: -0.8 - Math.random() * 1.6,
        life: 1, decay: 0.016, size: 3 + Math.random() * 7,
        color: 'rgba(226,244,238,0.28)', gravity: -0.03, steam: true
      });
    }
    push(rings, { life: 1, color: '#e8fff4' });
  };

  api.loot = function (rarity) {
    sound('loot');
    var colors = {
      commun: '#cfd6d0', rare: '#4da3ff', 'épique': '#c072ff',
      'légendaire': '#ffa12e', mythique: '#ff5f6d'
    };
    burst(150, 130, 18, colors[rarity] || '#ffd27a', 4.5);
    if (rarity === 'légendaire' || rarity === 'mythique') {
      api.banner('Butin ' + rarity, 'ÉQUIPEMENT', 'loot');
    }
  };

  api.levelUp = function (level) {
    sound('level');
    burst(150, 200, 20, '#9be89b', 4);
    api.banner('Rang ' + level, 'PROMOTION', 'level');
  };

  api.defeat = function () {
    sound('defeat');
    shake = 12;
    api.banner('Escouade décimée', 'REPLI', 'defeat');
  };

  api.chapter = function (name) {
    api.banner(name, 'NOUVEAU CHAPITRE', 'chapter');
  };

  /* Bannière plein écran, deux secondes, jamais deux à la fois. */
  var bannerTimer = null;
  api.banner = function (title, sub, kind) {
    var el = document.getElementById('fx-banner');
    if (!el) return;
    el.className = 'fx-banner ' + (kind || '');
    el.innerHTML = '<small></small><b></b>';
    el.querySelector('small').textContent = sub || '';
    el.querySelector('b').textContent = title;
    void el.offsetWidth;
    el.classList.add('show');
    clearTimeout(bannerTimer);
    bannerTimer = setTimeout(function () { el.classList.remove('show'); }, 1800);
  };

  /* Nombre flottant parti du point d'impact, taille selon l'importance. */
  api.floater = function (text, cls, weight) {
    var box = document.getElementById('floaters');
    if (!box || box.childNodes.length > 18) return;
    var el = document.createElement('div');
    el.className = 'floater' + (cls ? ' ' + cls : '');
    el.textContent = text;
    el.style.left = (38 + (Math.random() - 0.5) * 26) + '%';
    el.style.top = (34 + (Math.random() - 0.5) * 18) + '%';
    if (weight) el.style.fontSize = (13 + Math.min(9, weight * 9)) + 'px';
    box.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 950);
  };

  api.hitStopActive = function () { return hitStop > 0; };

  // ------------------------------------------------------------------
  // Rendu
  // ------------------------------------------------------------------

  var ENEMY_FILE = {
    titan: 'titan', anormal: 'anormal', rampant: 'anormal', colossal: 'colossal',
    blinde: 'blinde', feminin: 'feminin', bestial: 'bestial'
  };

  /* Point d'entrée utilisé par le jeu pour les deux canvas de l'arène. */
  window.Sprites = {
    draw: function (canvas, shape, palette, opts) {
      var ctx = canvas.getContext('2d');
      var w = canvas.width, h = canvas.height;
      ctx.clearRect(0, 0, w, h);
      ctx.imageSmoothingEnabled = true;
      if (canvas.id === 'hero-canvas' || shape === 'soldat') {
        drawHero(ctx, w, h, opts);
      } else {
        drawEnemy(ctx, w, h, shape, opts);
      }
    }
  };

  function drawHero(ctx, w, h, opts) {
    var portrait = window.__game ? window.__game.state.portrait : 19;
    var lunge = prefs.motion ? heroView.lunge : 0;
    ctx.save();
    ctx.translate(lunge * 6, (opts.bob || 0) * (prefs.motion ? 1 : 0) - lunge * 2);
    window.Art.draw(ctx, 'heroes', portrait, 0, 0, w, h);
    ctx.restore();
  }

  function drawEnemy(ctx, w, h, shape, opts) {
    var im = image('enemies/' + (ENEMY_FILE[shape] || 'titan') + '.webp');
    if (!im.complete || !im.naturalWidth) return;
    var motion = prefs.motion;
    var entry = motion ? enemyView.entry : 0;
    var recoil = motion ? enemyView.recoil : 0;
    var squash = motion ? enemyView.squash : 0;
    var dying = enemyView.dying;

    var scale = Math.min(w / im.naturalWidth, h / im.naturalHeight);
    var dw = im.naturalWidth * scale;
    var dh = im.naturalHeight * scale;
    var x = (w - dw) / 2;
    var y = h - dh;

    ctx.save();
    ctx.globalAlpha = Math.max(0, 1 - entry * 0.9) * (1 - dying * 0.85);
    // Entrée par la droite, recul sur les coups, dissolution à la mort.
    ctx.translate(x + dw / 2 + entry * 60 + recoil * 9, y + dh + (opts.bob || 0) * (motion ? 1 : 0));
    var sx = 1 + squash * 0.06 - dying * 0.05;
    var sy = 1 - squash * 0.08 + dying * 0.12;
    ctx.scale(sx, sy);
    ctx.drawImage(im, -dw / 2, -dh, dw, dh);
    if (enemyView.flash > 0.02) {
      // Flash blanc additif sur la silhouette : lisible même sur fond clair.
      ctx.globalCompositeOperation = 'source-atop';
      ctx.globalAlpha = enemyView.flash * 0.7;
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(-dw / 2, -dh, dw, dh);
    }
    ctx.restore();
  }

  /* Couche d'effets : lames, particules, ondes. Dessinée sur #battle-fx. */
  function drawEffects(fx, dt) {
    var ctx = fx.getContext('2d');
    var w = fx.width, h = fx.height;
    ctx.clearRect(0, 0, w, h);
    if (!prefs.motion) { particles.length = 0; slashes.length = 0; rings.length = 0; return; }

    slashes = slashes.filter(function (s) {
      s.life -= dt * 5;
      if (s.life <= 0) return false;
      var cx = w / 2, cy = h * 0.45;
      var len = w * 0.62;
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(s.angle);
      ctx.globalAlpha = Math.min(1, s.life);
      ctx.strokeStyle = s.crit ? '#ffe6a8' : '#dffbef';
      ctx.shadowColor = s.crit ? '#ffbe4d' : '#8ff3d4';
      ctx.shadowBlur = 16;
      ctx.lineWidth = s.width * s.life;
      ctx.lineCap = 'round';
      ctx.beginPath();
      // Arc de lame plutôt qu'un trait droit : la trajectoire se lit.
      ctx.moveTo(-len / 2, 26 * (1 - s.life));
      ctx.quadraticCurveTo(0, -34, len / 2, -18 * (1 - s.life));
      ctx.stroke();
      ctx.restore();
      return true;
    });

    rings = rings.filter(function (r) {
      r.life -= dt * 1.8;
      if (r.life <= 0) return false;
      ctx.save();
      ctx.globalAlpha = r.life * 0.3;
      ctx.strokeStyle = r.color;
      ctx.lineWidth = 3 * r.life;
      ctx.beginPath();
      ctx.ellipse(w / 2, h * 0.78, (1 - r.life) * w * 0.55, (1 - r.life) * h * 0.16, 0, 0, Math.PI * 2);
      ctx.stroke();
      ctx.restore();
      return true;
    });

    particles = particles.filter(function (p) {
      p.life -= p.decay * dt * 60;
      if (p.life <= 0) return false;
      p.x += p.vx * dt * 60;
      p.y += p.vy * dt * 60;
      p.vy += p.gravity * dt * 60;
      ctx.globalAlpha = Math.max(0, Math.min(1, p.life));
      ctx.fillStyle = p.color;
      if (p.steam) {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size * (1.6 - p.life), 0, Math.PI * 2);
        ctx.fill();
      } else {
        ctx.fillRect(p.x, p.y, p.size, p.size);
      }
      return true;
    });
    ctx.globalAlpha = 1;
  }

  /* Décor : deux couches de parallaxe et des braises qui dérivent. */
  var embers = [];
  function drawScene(bg, dt, arcImage, arcIndex) {
    var ctx = bg.getContext('2d');
    var w = bg.width, h = bg.height;
    ctx.clearRect(0, 0, w, h);
    if (arcImage && arcImage.complete && arcImage.naturalWidth) {
      var cols = 2, rows = 2;
      var sw = arcImage.naturalWidth / cols, sh = arcImage.naturalHeight / rows;
      var n = arcIndex % 4;
      var drift = prefs.motion ? Math.sin(parallax * 0.12) * 8 : 0;
      ctx.drawImage(arcImage, (n % cols) * sw, Math.floor(n / cols) * sh, sw, sh,
        -10 + drift, -6, w + 20, h + 12);
      ctx.fillStyle = 'rgba(9,16,20,0.35)';
      ctx.fillRect(0, 0, w, h);
    }
    if (!prefs.motion) return;
    if (embers.length < 26 && Math.random() < 0.4) {
      embers.push({ x: Math.random() * w, y: h + 10, v: 0.25 + Math.random() * 0.7, s: 1 + Math.random() * 2 });
    }
    ctx.fillStyle = 'rgba(226,196,138,0.5)';
    embers = embers.filter(function (e) {
      e.y -= e.v * dt * 60;
      e.x += Math.sin((e.y + parallax) * 0.02) * 0.3;
      if (e.y < -10) return false;
      ctx.globalAlpha = Math.min(0.6, e.y / h);
      ctx.fillRect(e.x, e.y, e.s, e.s);
      return true;
    });
    ctx.globalAlpha = 1;
  }

  // ------------------------------------------------------------------
  // Boucle d'effets
  // ------------------------------------------------------------------

  var environments = null;
  var lastFrame = 0;

  function decay(value, dt, rate) { return Math.max(0, value - dt * rate); }

  function frame(now) {
    requestAnimationFrame(frame);
    if (!frameGate(now, 'fx')) return;
    meterTick(now);
    var dt = lastFrame ? Math.min(0.05, (now - lastFrame) / 1000) : 0.016;
    lastFrame = now;
    if (document.hidden) return;

    parallax += dt * 12;
    hitStop = decay(hitStop, dt, 1);
    enemyView.entry = decay(enemyView.entry, dt, 3.2);
    enemyView.recoil = decay(enemyView.recoil, dt, 5);
    enemyView.squash = decay(enemyView.squash, dt, 4);
    enemyView.flash = decay(enemyView.flash, dt, 4.5);
    heroView.lunge = decay(heroView.lunge, dt, 6);
    if (enemyView.dying > 0) enemyView.dying = Math.max(0, enemyView.dying - dt * 2.2);
    shake = decay(shake, dt, 26);

    var arena = document.getElementById('arena');
    if (arena) {
      var s = prefs.motion ? shake : 0;
      arena.style.transform = s > 0.05
        ? 'translate(' + ((Math.random() - 0.5) * s).toFixed(2) + 'px,' + ((Math.random() - 0.5) * s * 0.7).toFixed(2) + 'px)'
        : '';
    }

    var bg = document.getElementById('scene-art');
    var fx = document.getElementById('battle-fx');
    if (!bg || !fx) return;
    var game = window.__game;
    var chapter = game && window.Content.CHAPTERS[game.state.chapter - 1];
    drawScene(bg, dt, environments, chapter ? chapter.arc : 0);
    drawEffects(fx, dt);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.toggle('reduced', !prefs.motion);
    environments = image('environments.webp');

    var arena = document.getElementById('arena');
    if (arena) {
      var bg = document.createElement('canvas');
      var fx = document.createElement('canvas');
      bg.id = 'scene-art'; fx.id = 'battle-fx';
      bg.width = fx.width = 300; bg.height = fx.height = 310;
      arena.prepend(bg);
      arena.append(fx);
      var banner = document.createElement('div');
      banner.id = 'fx-banner';
      banner.className = 'fx-banner';
      document.body.append(banner);
      var meter = document.createElement('div');
      meter.id = 'fps-meter';
      meter.hidden = true;
      document.body.append(meter);
    }
    requestAnimationFrame(frame);
  });

  window.Fx = api;
  // Compatibilité : le reste du jeu appelle encore Remaster.hit / Remaster.sound.
  window.Remaster = { hit: api.hit, sound: sound, prefs: prefs, savePrefs: savePrefs };
})();
