/* AOT IDLE — mise en scène : ouverture et jalons de campagne.

   La narration existait déjà dans les briefs de mission, mais elle n'était
   jamais jouée. Ici : une ouverture en trois plans à la première partie, et une
   carte de dialogue à chaque jalon d'arc — le texte vient de `content.js`, rien
   n'est inventé en double. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var queue = [];
  var showing = false;

  /* Ouverture : trois plans, sautable dès le premier. */
  var INTRO = [
    { art: 'bg-qg.webp', portrait: 22,
      title: 'An 845',
      text: 'Depuis cent ans, l\'humanité vit derrière trois murs. Les titans, eux, attendent.' },
    { art: 'bg-boss.webp', portrait: 0,
      title: 'La brèche',
      text: 'Le mur Maria tombe. Vous vous engagez dans le bataillon d\'exploration : '
        + 'des lames, du gaz, et une seule direction — dehors.' },
    { art: 'bg-front.webp', portrait: 5,
      title: 'Votre bataillon',
      text: 'Recrutez, entraînez, renaissez. Mille chapitres séparent Shiganshina de la mer.' }
  ];

  function panel(slide, index, total, onNext) {
    var sheet = $('story-sheet');
    sheet.className = 'sheet story-sheet';
    sheet.innerHTML = '';
    var box = document.createElement('div');
    box.className = 'story-panel';
    box.style.backgroundImage = 'linear-gradient(180deg, #0a1216cc, #0a1216f5), url(' + slide.art + ')';
    box.innerHTML = '<div class="story-face">' + Art.icon('heroes', slide.portrait) + '</div>'
      + '<small></small><h2></h2><p></p>'
      + '<div class="story-actions"></div>';
    box.querySelector('small').textContent = slide.tag || ('Ouverture ' + (index + 1) + ' / ' + total);
    box.querySelector('h2').textContent = slide.title;
    box.querySelector('p').textContent = slide.text;

    var actions = box.querySelector('.story-actions');
    var next = document.createElement('button');
    next.className = 'big-btn';
    next.textContent = index + 1 < total ? 'Suivant' : 'Rejoindre le bataillon';
    next.onclick = onNext;
    actions.append(next);
    if (index + 1 < total) {
      var skip = document.createElement('button');
      skip.className = 'ghost-btn';
      skip.textContent = 'Passer';
      skip.onclick = function () { close(); };
      actions.append(skip);
    }
    sheet.append(box);
    sheet.classList.remove('hidden');
    if (window.Fx) window.Fx.sound('ui');
  }

  function close() {
    var sheet = $('story-sheet');
    sheet.classList.add('hidden');
    sheet.innerHTML = '';
    showing = false;
    step();
  }

  function step() {
    if (showing || !queue.length) return;
    showing = true;
    var item = queue.shift();
    item();
  }

  function playIntro() {
    var index = 0;
    function advance() {
      if (index >= INTRO.length) { close(); return; }
      var slide = INTRO[index];
      panel(slide, index, INTRO.length, function () { index++; advance(); });
    }
    advance();
  }

  /* Jalon : le dernier chapitre d'une phase d'arc (un sur vingt-cinq). */
  function milestoneSlide(chapter) {
    var chapters = window.Content.CHAPTERS;
    var def = chapters[chapter - 1];
    if (!def || !def.milestone) return null;
    var arc = window.Content.ARCS[def.arc];
    var faces = [0, 1, 3, 12, 10, 9, 13, 14, 5, 2];
    return {
      art: ['bg-qg.webp', 'bg-front.webp', 'bg-boss.webp', 'bg-arene.webp'][def.arc % 4],
      portrait: faces[def.arc % faces.length],
      tag: 'Jalon · ' + arc[0] + ' · ' + arc[1],
      title: def.name,
      text: arc[3]
    };
  }

  window.Story = {
    /* Appelé par le jeu quand un chapitre s'ouvre. */
    chapter: function (chapter) {
      var slide = milestoneSlide(chapter);
      if (!slide) return;
      var seen = window.__game.state.seen || {};
      if (seen['c' + chapter]) return;
      seen['c' + chapter] = 1;
      window.__game.state.seen = seen;
      window.__game.save();
      queue.push(function () {
        panel(slide, 0, 1, close);
        if (window.Fx) window.Fx.banner(slide.title, 'JALON DE CAMPAGNE', 'chapter');
      });
      step();
    },
    intro: function (force) {
      var state = window.__game.state;
      var seen = state.seen || {};
      if (seen.intro && !force) return;
      seen.intro = 1;
      state.seen = seen;
      window.__game.save();
      queue.push(playIntro);
      step();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var sheet = document.createElement('div');
    sheet.id = 'story-sheet';
    sheet.className = 'sheet story-sheet hidden';
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    document.body.append(sheet);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !sheet.classList.contains('hidden')) close();
    });
  });
})();
