/* Sprites pixel dessinés à la main : chaque sprite est une grille de caractères
   associée à une palette. Rendu sur <canvas> en nearest-neighbor. */
(function () {
  'use strict';

  // Palettes : p = peau, o = ombre de peau, c = cheveux, y = yeux, b = bouche,
  // a = armure/plaque, d = détail clair, v = vapeur / aura.
  var PALETTES = {
    chair:   { p: '#e8b48c', o: '#b8825f', c: '#4a3222', y: '#2a1810', b: '#7a2a2a', a: '#9a5f3f', d: '#ffd9b8', v: '#f2e3d0' },
    pale:    { p: '#d9c3a5', o: '#a68b6d', c: '#2e2418', y: '#3b1a1a', b: '#5e2020', a: '#8a7458', d: '#f3e6d0', v: '#e8f0ff' },
    brulee:  { p: '#c96a4a', o: '#8c3f2a', c: '#1d1410', y: '#ffcf3a', b: '#3a1008', a: '#7a3520', d: '#ff9b6a', v: '#ffb27a' },
    cendre:  { p: '#8f8f96', o: '#5c5c63', c: '#20202a', y: '#b8e4ff', b: '#101018', a: '#6a6a74', d: '#c8c8d2', v: '#dfe6f2' },
    sang:    { p: '#b8574f', o: '#7d2f2b', c: '#170e0e', y: '#ffe45c', b: '#2a0a0a', a: '#75322c', d: '#e88a80', v: '#ff9c8a' },
    givre:   { p: '#a9c8d8', o: '#6d8fa4', c: '#243642', y: '#eaffff', b: '#12242c', a: '#7e9fb4', d: '#d8f0ff', v: '#eaffff' },
    or:      { p: '#d8a355', o: '#9a6c2c', c: '#3a2a10', y: '#fff4c0', b: '#2a1c06', a: '#b4842f', d: '#ffdc8a', v: '#ffeeb0' },
    ombre:   { p: '#6b5b7b', o: '#453a54', c: '#1a1422', y: '#ff5a7a', b: '#120d18', a: '#544468', d: '#9b87ad', v: '#c6a8ff' },
    soldat:  { p: '#e8b48c', o: '#3a2b1c', c: '#5a3a1e', y: '#2a1810', b: '#8a4a44', a: '#cfc6b4', d: '#6b4a2a', v: '#4f6b34' }
  };

  // Corps humanoïde standard (titan « normal »), 16 x 20.
  var TITAN = [
    '................',
    '.....cccccc.....',
    '....cccccccc....',
    '...ccpppppcc....',
    '...cpppppppc....',
    '...pyyppyypp....',
    '...pppppppppp...',
    '...pbdbdbdbp....',
    '....pbbbbbp.....',
    '.....pppp.......',
    '...pppppppp.....',
    '..opppppppp o...',
    '..opppppppppo...',
    '..opppppppppo...',
    '...pppppppp.....',
    '...pppppppp.....',
    '....pp..pp......',
    '....pp..pp......',
    '...opp..ppo.....',
    '...ooo..ooo.....'
  ];

  // Titan anormal : membres allongés, tête penchée.
  var ANORMAL = [
    '................',
    '......cccc......',
    '.....cccccc.....',
    '....ccppppc.....',
    '....cppyppc.....',
    '....pppyppp.....',
    '....pbbbbpp.....',
    '.....pppp.......',
    '..o..pppp..o....',
    '..op.pppp.po....',
    '..oppppppppo....',
    '...opppppp o....',
    '...pppppppp.....',
    '...pppppppp.....',
    '....pp..pp......',
    '....pp..pp......',
    '....pp..pp......',
    '...opp..ppo.....',
    '...opp..ppo.....',
    '...ooo..ooo.....'
  ];

  // Titan rampant / difforme.
  var RAMPANT = [
    '................',
    '................',
    '................',
    '.....cccccc.....',
    '....cpppppc.....',
    '....pyppyp......',
    '....pbbbbp......',
    '...pppppppp.....',
    '..oppppppppo....',
    '.opppppppppp o..',
    '.oppppppppppo...',
    '..pppppppppp....',
    '..pp..pp..pp....',
    '..oo..oo..oo....',
    '................',
    '................',
    '................',
    '................',
    '................',
    '................'
  ];

  // Titan cuirassé : plaques sur tout le corps.
  var BLINDE = [
    '................',
    '.....aaaaaa.....',
    '....aaaaaaaa....',
    '...aaayaayaa....',
    '...aaaaaaaaa....',
    '...aadddddaa....',
    '....aaaaaaa.....',
    '...aaaaaaaaa....',
    '..aaadddddaaa...',
    '..aaadddddaaa...',
    '..aaaaaaaaaaa...',
    '..oaaaaaaaaao...',
    '...aaaaaaaaa....',
    '...aaaaaaaaa....',
    '...aaa...aaa....',
    '...aaa...aaa....',
    '...aaa...aaa....',
    '..oaaa...aaao...',
    '..ooo.....ooo...',
    '................'
  ];

  // Titan colossal : immense, vapeur, muscles apparents.
  var COLOSSAL = [
    '..vv..........vv',
    '.v.vv..vvvv..vv.',
    '....v.vvvvvv.v..',
    '.....vppppppv...',
    '.....pyppppy....',
    '.....pppppppp...',
    '.....pbbbbbbp...',
    '....pppppppppp..',
    '...opppppppppo..',
    '..oppdppppdppo..',
    '..opppppppppppo.',
    '..opppppppppppo.',
    '...opppppppppo..',
    '....pppppppppp..',
    '....ppp...pppp..',
    '....ppp...pppp..',
    '....ppp...pppp..',
    '...oppp...pppo..',
    '...oppp...pppo..',
    '...ooo.....ooo..'
  ];

  // Titan féminin : silhouette fine, muscles saillants.
  var FEMININ = [
    '................',
    '.....cccccc.....',
    '....cccccccc....',
    '....cppppppc....',
    '....pyppppy.....',
    '....pppppppp....',
    '.....pbbbbp.....',
    '.....pppppp.....',
    '....dppppppd....',
    '...oppppppppo...',
    '...opppppppo....',
    '....pppppppp....',
    '....pppppppp....',
    '.....pppppp.....',
    '....ppp..ppp....',
    '....ppp..ppp....',
    '....ppp..ppp....',
    '...oppp..pppo...',
    '...oppp..pppo...',
    '...ooo....ooo...'
  ];

  // Titan bestial : fourrure, longs bras, museau.
  var BESTIAL = [
    '................',
    '...cccc..cccc...',
    '..cccccccccccc..',
    '..cccpppppcccc..',
    '..ccpyppypccc...',
    '..ccppppppccc...',
    '...cpbbbbpcc....',
    '...ccppppcc.....',
    '..cccccccccc....',
    '.occcccccccco...',
    'occcccccccccco..',
    'occcccccccccco..',
    '.occcccccccco...',
    '..cccccccccc....',
    '..cccc..cccc....',
    '..cccc..cccc....',
    '..cccc..cccc....',
    '.occcc..cccco...',
    '.occcc..cccco...',
    '.ooo......ooo...'
  ];

  // Titan mâchoire : petit, rapide, gueule énorme.
  var MACHOIRE = [
    '................',
    '................',
    '.....cccccc.....',
    '....cccccccc....',
    '....cpyppypc....',
    '....pppppppp....',
    '....dbdbdbdb....',
    '....bdbdbdbd....',
    '....dbdbdbdb....',
    '.....pppppp.....',
    '...opppppppo....',
    '..opppppppppo...',
    '..opppppppppo...',
    '...pppppppp.....',
    '....pp..pp......',
    '....pp..pp......',
    '...opp..ppo.....',
    '...opp..ppo.....',
    '...ooo..ooo.....',
    '................'
  ];

  // Titan charrette : quadrupède.
  var CHARRETTE = [
    '................',
    '................',
    '..cccc..........',
    '.cppppc.........',
    '.cpyppc.........',
    '.cpbbpc.........',
    '..cppcccccccc...',
    '..ccccccccccccc.',
    '.occcccccccccco.',
    '.occcccccccccco.',
    '..ccccccccccccc.',
    '..cc..cc..cc..c.',
    '..cc..cc..cc..c.',
    '..cc..cc..cc..c.',
    '..oo..oo..oo..o.',
    '................',
    '................',
    '................',
    '................',
    '................'
  ];

  // Titan marteau d'ouvrier : cristal et structure.
  var MARTEAU = [
    '................',
    '.......dd.......',
    '......dddd......',
    '.....dd..dd.....',
    '....dccccccd....',
    '....cppyyppc....',
    '....cpppppp.....',
    '....dpbbbbpd....',
    '...ddppppppdd...',
    '..oddpppppp ddo.',
    '..oddppppppddo..',
    '...ddpppppp d...',
    '....pppppppp....',
    '....dppppppd....',
    '....ddd..ddd....',
    '....ddd..ddd....',
    '....ddd..ddd....',
    '...oddd..dddo...',
    '...ooo....ooo...',
    '................'
  ];

  // Titan originel : couronné d'os, aura.
  var ORIGINEL = [
    'v..............v',
    '.v...dddddd...v.',
    '..v.dcccccccd.v.',
    '...dcccccccccd..',
    '...ccpyppypcc...',
    '...cppppppppc...',
    '....pbbbbbbp....',
    '...dppppppppd...',
    '..dpppppppppp d.',
    '.odppppppppppdo.',
    '.odppppppppppdo.',
    '..dpppppppppp d.',
    '...pppppppppp...',
    '...pppp..pppp...',
    '...pppp..pppp...',
    '...pppp..pppp...',
    '..odppp..pppdo..',
    '..odppp..pppdo..',
    '..ooo......ooo..',
    '................'
  ];

  // Le héros : soldat du bataillon d'exploration, cape et lames.
  var SOLDAT = [
    '................',
    '......cccc......',
    '.....cccccc.....',
    '.....cppppc.....',
    '.....pyppy p....',
    '.....pppppp.....',
    '......pbbp......',
    '...vvaaaaaavv...',
    '..vvdaaaaaadvv..',
    '..vvdaaaaaadvv..',
    '..vv.aaaaaa.vv..',
    '..vv.adddda.vv..',
    '...v.aa..aa.v...',
    '.....aa..aa.....',
    '.....dd..dd.....',
    '.....aa..aa.....',
    '.....aa..aa.....',
    '....oaa..aao....',
    '....ooo..ooo....',
    '................'
  ];

  var SHAPES = {
    titan: TITAN,
    anormal: ANORMAL,
    rampant: RAMPANT,
    blinde: BLINDE,
    colossal: COLOSSAL,
    feminin: FEMININ,
    bestial: BESTIAL,
    machoire: MACHOIRE,
    charrette: CHARRETTE,
    marteau: MARTEAU,
    originel: ORIGINEL,
    soldat: SOLDAT
  };

  /* Dessine un sprite dans un canvas. `flash` (0..1) blanchit la silhouette
     quand l'ennemi encaisse un coup. */
  function draw(canvas, shapeName, paletteName, options) {
    var opts = options || {};
    var rows = SHAPES[shapeName] || SHAPES.titan;
    var palette = PALETTES[paletteName] || PALETTES.chair;
    var w = rows[0].length;
    var h = rows.length;
    var scale = opts.scale || Math.max(1, Math.floor(canvas.width / w));
    var ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = false;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    var offsetX = Math.floor((canvas.width - w * scale) / 2);
    var offsetY = Math.floor((canvas.height - h * scale) / 2) + (opts.bob || 0);
    var flash = Math.min(0.65, opts.flash || 0);

    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        var ch = rows[y].charAt(x);
        if (ch === '.' || ch === ' ') continue;
        var color = palette[ch] || palette.p;
        if (flash > 0) color = mix(color, '#ffffff', flash);
        ctx.fillStyle = color;
        ctx.fillRect(offsetX + x * scale, offsetY + y * scale, scale, scale);
      }
    }
  }

  function mix(hex, other, amount) {
    var a = parse(hex), b = parse(other);
    return 'rgb(' + Math.round(a[0] + (b[0] - a[0]) * amount) + ','
      + Math.round(a[1] + (b[1] - a[1]) * amount) + ','
      + Math.round(a[2] + (b[2] - a[2]) * amount) + ')';
  }

  function parse(hex) {
    return [parseInt(hex.substr(1, 2), 16), parseInt(hex.substr(3, 2), 16), parseInt(hex.substr(5, 2), 16)];
  }

  window.Sprites = { draw: draw, shapes: SHAPES, palettes: PALETTES };
})();
