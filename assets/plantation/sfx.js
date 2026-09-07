/* Ambiance et effets sonores de la serre.
   Tout est synthétisé en Web Audio : aucun fichier audio à héberger, aucune requête réseau.
   Le son ne démarre qu'après un geste de l'utilisateur (politique d'autoplay des navigateurs). */
(function(root){
 'use strict';
 const KEY='gp-sound-v1',VOL='gp-volume-v1';
 const AC=root.AudioContext||root.webkitAudioContext;
 let ctx=null,master=null,ambientGain=null,layers={},on=read(KEY,'1')==='1',volume=clamp(parseFloat(read(VOL,'0.75'))||.75,0,1),started=false;

 function read(key,fallback){try{const v=localStorage.getItem(key);return v===null?fallback:v;}catch{return fallback;}}
 function write(key,value){try{localStorage.setItem(key,value);}catch{}}
 function clamp(v,a,b){return Math.max(a,Math.min(b,v));}

 let noiseBuffer=null;
 function noise(){
  if(!noiseBuffer){noiseBuffer=ctx.createBuffer(1,ctx.sampleRate*2,ctx.sampleRate);const d=noiseBuffer.getChannelData(0);
   let last=0;for(let i=0;i<d.length;i++){const w=Math.random()*2-1;last=(last+w*.02)/1.02;d[i]=Math.max(-1,Math.min(1,w*.5+last*2));}}
  const src=ctx.createBufferSource();src.buffer=noiseBuffer;src.loop=true;return src;
 }
 function ensure(){
  if(ctx||!AC)return ctx;
  ctx=new AC();
  master=ctx.createGain();master.gain.value=on?volume:0;
  const comp=ctx.createDynamicsCompressor();comp.threshold.value=-16;comp.ratio.value=6;comp.attack.value=.004;comp.release.value=.2;
  master.connect(comp);comp.connect(ctx.destination);
  ambientGain=ctx.createGain();ambientGain.gain.value=0;ambientGain.connect(master);
  return ctx;
 }
 /* Petit réverb par convolution : donne de l'air à la serre sans échantillon externe. */
 let verb=null;
 function reverb(){
  if(verb)return verb;
  const len=ctx.sampleRate*1.5,buf=ctx.createBuffer(2,len,ctx.sampleRate);
  for(let c=0;c<2;c++){const d=buf.getChannelData(c);for(let i=0;i<len;i++)d[i]=(Math.random()*2-1)*Math.pow(1-i/len,2.6);}
  verb=ctx.createConvolver();verb.buffer=buf;
  const wet=ctx.createGain();wet.gain.value=.22;verb.connect(wet);wet.connect(master);
  return verb;
 }
 function tone(freq,{type='sine',at=0,dur=.25,gain=.2,to=null,attack=.006,curve='exp',pan=0,send=0}={}){
  const t=ctx.currentTime+at,osc=ctx.createOscillator(),g=ctx.createGain();
  osc.type=type;osc.frequency.setValueAtTime(freq,t);
  if(to)osc.frequency.exponentialRampToValueAtTime(Math.max(1,to),t+dur);
  g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(Math.max(.0002,gain),t+attack);
  if(curve==='exp')g.gain.exponentialRampToValueAtTime(.0001,t+dur);else g.gain.linearRampToValueAtTime(.0001,t+dur);
  let node=g;
  if(pan&&ctx.createStereoPanner){const p=ctx.createStereoPanner();p.pan.value=clamp(pan,-1,1);g.connect(p);node=p;}
  osc.connect(g);node.connect(master);
  if(send)node.connect(reverb());
  osc.start(t);osc.stop(t+dur+.05);
  return osc;
 }
 function hiss({at=0,dur=.4,gain=.15,type='bandpass',freq=1200,to=null,q=1.2,send=0}={}){
  const t=ctx.currentTime+at,src=noise(),f=ctx.createBiquadFilter(),g=ctx.createGain();
  f.type=type;f.Q.value=q;f.frequency.setValueAtTime(freq,t);
  if(to)f.frequency.exponentialRampToValueAtTime(Math.max(40,to),t+dur);
  g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(Math.max(.0002,gain),t+dur*.16);
  g.gain.exponentialRampToValueAtTime(.0001,t+dur);
  src.connect(f);f.connect(g);g.connect(master);if(send)g.connect(reverb());
  src.start(t);src.stop(t+dur+.05);
  return src;
 }
 const bell=(freq,at,gain=.16,dur=1.5)=>{
  const t=ctx.currentTime+at,carrier=ctx.createOscillator(),mod=ctx.createOscillator(),depth=ctx.createGain(),g=ctx.createGain();
  mod.frequency.value=freq*2.76;depth.gain.setValueAtTime(freq*1.8,t);depth.gain.exponentialRampToValueAtTime(1,t+dur*.5);
  mod.connect(depth);depth.connect(carrier.frequency);carrier.frequency.value=freq;
  g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(gain,t+.008);g.gain.exponentialRampToValueAtTime(.0001,t+dur);
  carrier.connect(g);g.connect(master);g.connect(reverb());
  carrier.start(t);mod.start(t);carrier.stop(t+dur+.05);mod.stop(t+dur+.05);
 };

 const sounds={
  ui:()=>{tone(760,{type:'triangle',dur:.07,gain:.05,to:900});},
  tab:()=>{tone(520,{type:'triangle',dur:.09,gain:.05,to:640});},
  soil:()=>{hiss({dur:.55,gain:.3,type:'lowpass',freq:1400,to:380,q:.8});
   for(let i=0;i<7;i++)hiss({at:i*.06+Math.random()*.03,dur:.09,gain:.1,type:'bandpass',freq:700+Math.random()*900,q:3});
   tone(88,{type:'sine',dur:.32,gain:.16,to:56});},
  plant:()=>{tone(210,{type:'sine',dur:.22,gain:.15,to:130});hiss({at:.04,dur:.28,gain:.13,type:'highpass',freq:2400,q:.7});
   tone(520,{type:'triangle',at:.06,dur:.2,gain:.05,to:660,send:1});},
  water:()=>{hiss({dur:1,gain:.27,type:'bandpass',freq:1900,to:520,q:1.1,send:1});
   for(let i=0;i<6;i++){const f=760+Math.random()*900;tone(f,{type:'sine',at:.12+i*.13+Math.random()*.05,dur:.16,gain:.07,to:f*.55,send:1});}},
  feed:()=>{[880,1320,1760].forEach((f,i)=>tone(f,{type:'triangle',at:i*.07,dur:.5,gain:.09,send:1}));
   hiss({at:.02,dur:.5,gain:.1,type:'highpass',freq:5200});},
  harvest:()=>{[0,.13].forEach(at=>{hiss({at,dur:.07,gain:.24,type:'highpass',freq:4200,q:2});
    tone(2400,{type:'square',at,dur:.06,gain:.04,to:1500});});
   [523.25,659.25,784,1046.5].forEach((f,i)=>tone(f,{type:'triangle',at:.26+i*.06,dur:.7,gain:.09,send:1}));
   tone(120,{type:'sine',at:.26,dur:.4,gain:.12,to:80});},
  cross:()=>{tone(180,{type:'sawtooth',dur:.75,gain:.07,to:920,send:1});
   hiss({dur:.8,gain:.14,type:'bandpass',freq:600,to:4200,q:2});
   bell(1174.7,.72,.13);bell(1567.98,.8,.1);},
  buy:()=>{tone(1050,{type:'square',dur:.09,gain:.07});tone(1420,{type:'square',at:.08,dur:.18,gain:.06,send:1});},
  refill:()=>{hiss({dur:.9,gain:.3,type:'bandpass',freq:420,to:1500,q:1.1,send:1});
   for(let i=0;i<5;i++)tone(500+i*90,{type:'sine',at:.1+i*.15,dur:.14,gain:.04});},
  pot:()=>{tone(150,{type:'sine',dur:.25,gain:.16,to:95});hiss({dur:.09,gain:.2,type:'bandpass',freq:1800,q:1.5});
   bell(880,.14,.12);bell(1318.5,.26,.1);},
  accessory:()=>{tone(300,{type:'square',dur:.06,gain:.05});tone(240,{type:'square',at:.07,dur:.09,gain:.05});
   bell(1046.5,.16,.1);},
  ready:()=>{bell(987.77,0,.15,1.8);bell(1318.5,.14,.11,1.6);},
  discovery:()=>{[659.25,830.61,987.77,1318.5].forEach((f,i)=>bell(f,i*.11,.13,2));},
  error:()=>{tone(196,{type:'square',dur:.14,gain:.08});tone(146.83,{type:'square',at:.13,dur:.22,gain:.08});}
 };

 /* Nappe d'ambiance : souffle de serre, bourdon de lampe, ventilateur et brumisateur. */
 function startAmbient(){
  if(started||!ctx)return;started=true;
  const air=noise(),airFilter=ctx.createBiquadFilter(),airGain=ctx.createGain();
  airFilter.type='lowpass';airFilter.frequency.value=520;airFilter.Q.value=.6;
  airGain.gain.value=.05;air.connect(airFilter);airFilter.connect(airGain);airGain.connect(ambientGain);air.start();
  const lfo=ctx.createOscillator(),lfoGain=ctx.createGain();
  lfo.frequency.value=.06;lfoGain.gain.value=.022;lfo.connect(lfoGain);lfoGain.connect(airGain.gain);lfo.start();
  const pad=ctx.createGain();pad.gain.value=.035;pad.connect(ambientGain);
  [55,82.5,110.3].forEach((f,i)=>{const o=ctx.createOscillator(),g=ctx.createGain();o.type='sine';o.frequency.value=f;g.gain.value=.4-i*.1;
   const wob=ctx.createOscillator(),wg=ctx.createGain();wob.frequency.value=.05+i*.03;wg.gain.value=f*.004;wob.connect(wg);wg.connect(o.frequency);wob.start();
   o.connect(g);g.connect(pad);o.start();});
  layers.lamp=sub(()=>{const o=ctx.createOscillator(),g=ctx.createGain();o.type='sine';o.frequency.value=100;g.gain.value=.5;o.connect(g);o.start();return g;});
  layers.fan=sub(()=>{const src=noise(),f=ctx.createBiquadFilter(),g=ctx.createGain();f.type='bandpass';f.frequency.value=420;f.Q.value=1.4;
   const swirl=ctx.createOscillator(),sg=ctx.createGain();swirl.frequency.value=7.5;sg.gain.value=90;swirl.connect(sg);sg.connect(f.frequency);swirl.start();
   src.connect(f);f.connect(g);src.start();return g;});
  layers.humidifier=sub(()=>{const src=noise(),f=ctx.createBiquadFilter(),g=ctx.createGain();f.type='highpass';f.frequency.value=3800;
   src.connect(f);f.connect(g);src.start();return g;});
  ambientGain.gain.setTargetAtTime(.55,ctx.currentTime,2);
 }
 function sub(make){const g=ctx.createGain();g.gain.value=0;const source=make();source.connect(g);g.connect(ambientGain);return g;}
 function fade(layer,value){if(layers[layer])layers[layer].gain.setTargetAtTime(value,ctx.currentTime,.6);}

 const api={
  /** À appeler sur le premier geste utilisateur : débloque le contexte audio. */
  unlock(){if(!on)return;const c=ensure();if(!c)return;if(c.state==='suspended')c.resume();startAmbient();},
  play(name){
   if(!on||!sounds[name])return;
   const c=ensure();if(!c)return;
   if(c.state==='suspended'){c.resume();return;}
   try{sounds[name]();}catch{}
  },
  /** Reflète l'état de la serre dans l'ambiance (accessoires installés, lampe). */
  scene(state,lit){
   if(!ctx||!started)return;
   fade('fan',state?.accessories?.fan?.09:0);
   fade('humidifier',state?.accessories?.humidifier?.05:0);
   fade('lamp',lit?.02:0);
  },
  enabled(){return on;},
  /* Exposé pour le débogage : contexte audio réellement utilisé. */
  context(){return ctx;},
  volume(v){if(v!==undefined){volume=clamp(v,0,1);write(VOL,String(volume));if(master)master.gain.setTargetAtTime(on?volume:0,ctx.currentTime,.05);}return volume;},
  toggle(){
   on=!on;write(KEY,on?'1':'0');
   if(on){const c=ensure();if(c){if(c.state==='suspended')c.resume();startAmbient();master.gain.setTargetAtTime(volume,c.currentTime,.15);}api.play('ui');}
   else if(master)master.gain.setTargetAtTime(0,ctx.currentTime,.15);
   return on;
  }
 };
 root.GPSound=api;
})(window);
