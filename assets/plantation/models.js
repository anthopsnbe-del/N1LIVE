(function(root){
 'use strict';
 const T=root.THREE;
 if(!T){root.GPModels=null;return;}
 const mat=(color,extra={})=>{const m=new T.MeshStandardMaterial({color,roughness:.82,metalness:0,...extra});m.color.convertSRGBToLinear();m.emissive.convertSRGBToLinear();return m;};
 const rnd=seed=>()=>{seed|=0;seed=seed+0x6D2B79F5|0;let t=Math.imul(seed^seed>>>15,1|seed);t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296;};
 function mesh(geo,material,parent,x=0,y=0,z=0){const o=new T.Mesh(geo,material);o.position.set(x,y,z);o.castShadow=true;o.receiveShadow=true;if(parent)parent.add(o);return o;}
 function box(parent,w,h,d,m,x=0,y=0,z=0){return mesh(new T.BoxGeometry(w,h,d),m,parent,x,y,z);}
 function tube(parent,a,b,r,m){const delta=new T.Vector3().subVectors(b,a);const o=mesh(new T.CylinderGeometry(r*.75,r,delta.length(),7),m,parent);o.position.copy(a).add(b).multiplyScalar(.5);o.quaternion.setFromUnitVectors(new T.Vector3(0,1,0),delta.normalize());return o;}
 let textureDone;root.GPTexturesReady=new Promise(resolve=>textureDone=resolve);
 const surface=new T.TextureLoader().load(new URL('bud-surface.webp',document.currentScript.src).href,textureDone,undefined,textureDone);
 surface.encoding=T.sRGBEncoding;surface.wrapS=surface.wrapT=T.RepeatWrapping;surface.repeat.set(1.3,1.3);

 /* Ressources partagées : jamais libérées par dispose(), réutilisées par tous les buds. */
 const CACHE=new Map();
 const cached=(key,make)=>{let o=CACHE.get(key);if(!o){o=make();o.userData.shared=true;CACHE.set(key,o);}return o;};
 const YAXIS=new T.Vector3(0,1,0),ZAXIS=new T.Vector3(0,0,1);
 /* Profil d'une cola : épaules basses larges, pointe effilée. */
 const cola=t=>{t=Math.min(1,Math.max(0,t));return Math.pow(Math.sin(Math.PI*Math.pow(t,.74)),.8);};
 const calyxGeo=()=>cached('calyx',()=>{const geo=new T.IcosahedronGeometry(1,1),p=geo.attributes.position;
  for(let i=0;i<p.count;i++){const x=p.getX(i),y=p.getY(i),z=p.getZ(i),k=1-Math.max(0,y)*.46;p.setXYZ(i,x*k*.9,y*1.2,z*k*.93);}
  p.needsUpdate=true;geo.computeVertexNormals();return geo;});
 const pistilGeo=()=>cached('pistil',()=>new T.TubeGeometry(new T.CatmullRomCurve3([new T.Vector3(0,0,0),new T.Vector3(.008,.042,.005),new T.Vector3(.038,.076,.015),new T.Vector3(.088,.09,.028)]),9,.0026,4,false));
 const stalkGeo=()=>cached('stalk',()=>new T.CylinderGeometry(.0015,.0022,.014,4));
 const headGeo=()=>cached('head',()=>new T.IcosahedronGeometry(.0052,0));
 const sugarGeo=()=>cached('sugar',()=>{const s=new T.Shape();s.moveTo(0,0);s.lineTo(.06,.08);s.lineTo(.035,.11);s.lineTo(.07,.16);s.lineTo(0,.38);s.lineTo(-.07,.16);s.lineTo(-.035,.11);s.lineTo(-.06,.08);s.closePath();return new T.ShapeGeometry(s);});
 const frost=(color,extra={})=>{const m=new T.MeshPhysicalMaterial({color,roughness:.14,metalness:0,clearcoat:1,clearcoatRoughness:.08,...extra});m.color.convertSRGBToLinear();m.emissive.convertSRGBToLinear();return m;};

 /** Bud procédural : calices en verticilles, pistils recourbés, trichomes à tête. */
 function bud(v,size=1){
  const g=new T.Group(),r=rnd(v.seed),detail=size>.5,hybrid=(v.parents||[]).length>0;
  const H=1.34,dummy=new T.Object3D(),tone=new T.Color(),spin=new T.Quaternion(),tilt=new T.Quaternion();
  const base=new T.Color(v.bud);
  const tint=base.clone().lerp(new T.Color('#c1c8a2'),.3),
   deep=base.clone().lerp(new T.Color('#16210f'),.55),
   bright=base.clone().lerp(new T.Color('#eaf1c4'),.34);
  const skin=mat('#ffffff',{map:surface,bumpMap:surface,bumpScale:.032,roughness:.92});
  const core=mesh(new T.SphereGeometry(1,16,14),mat(deep.getStyle(),{map:surface,roughness:.96}),g,0,.64,0);
  core.scale.set(.3,.72,.29);core.receiveShadow=false;
  tube(g,new T.Vector3(0,-.09,0),new T.Vector3(0,.16,0),.024,mat('#5f7a38'));

  const nodes=detail?15:6,calyxCount=detail?430:110,pistilCount=detail?230:44,
   frostCount=detail?(hybrid?520:430):(hybrid?110:90),leaves=detail?22:6;
  const lobes=new T.InstancedMesh(calyxGeo(),skin,calyxCount);
  const hairs=new T.InstancedMesh(pistilGeo(),mat('#ffffff',{roughness:.7}),pistilCount);
  const stalks=new T.InstancedMesh(stalkGeo(),mat('#dfe4cb',{roughness:.55}),frostCount);
  const heads=new T.InstancedMesh(headGeo(),frost('#f6f9ea',{emissive:'#7d8f5c',emissiveIntensity:.18}),frostCount);

  for(let i=0;i<calyxCount;i++){
   const n=i%nodes,t=(n+.6)/nodes+(r()-.5)*.05,y=t*H,bump=1+.13*Math.sin(y*6.5+n*1.7)+.07*Math.sin(y*17.3);
   const w=(.085+cola(t)*.24)*bump;
   const a=i*2.39996+n*.87+r()*.45,out=w*(.5+r()*.6);
   dummy.position.set(Math.cos(a)*out,y+(r()-.5)*.07,Math.sin(a)*out);
   const s=(.036+r()*.026)*(.7+cola(t)*.6);
   dummy.scale.set(s*(1.02+r()*.3),s*(1.12+r()*.8),s*(.98+r()*.28));
   dummy.quaternion.copy(spin.setFromAxisAngle(YAXIS,-a+(r()-.5)*.5)).multiply(tilt.setFromAxisAngle(ZAXIS,-(.2+(1-t)*.6+r()*.45)));
   dummy.updateMatrix();lobes.setMatrixAt(i,dummy.matrix);
   tone.copy(r()<.5?deep:bright).lerp(tint,.3+r()*.55).convertSRGBToLinear();lobes.setColorAt(i,tone);
  }
  if(lobes.instanceColor)lobes.instanceColor.needsUpdate=true;

  const pistil=new T.Color(v.pistil),pistilTip=pistil.clone().lerp(new T.Color('#fff3dc'),.45);
  for(let i=0;i<pistilCount;i++){
   const t=.14+Math.pow(r(),.62)*.82,y=t*H,w=.1+cola(t)*.245,a=r()*6.283;
   dummy.position.set(Math.cos(a)*w*.72,y,Math.sin(a)*w*.72);
   const len=.9+r()*1.1;dummy.scale.set(.9+r()*.4,len,.9+r()*.4);
   dummy.quaternion.copy(spin.setFromAxisAngle(YAXIS,-a+(r()-.5)*.8)).multiply(tilt.setFromAxisAngle(ZAXIS,-(.05+r()*1.05)));
   dummy.updateMatrix();hairs.setMatrixAt(i,dummy.matrix);
   tone.copy(r()<.3?pistilTip:pistil).lerp(bright,r()*.12).convertSRGBToLinear();hairs.setColorAt(i,tone);
  }
  if(hairs.instanceColor)hairs.instanceColor.needsUpdate=true;

  const dir=new T.Vector3(),pos=new T.Vector3();
  for(let i=0;i<frostCount;i++){
   const t=.05+r()*.95,y=t*H,w=.1+cola(t)*.25,a=r()*6.283,lean=.3+r()*1.1;
   dir.set(Math.cos(a)*Math.cos(lean),Math.sin(lean),Math.sin(a)*Math.cos(lean)).normalize();
   pos.set(Math.cos(a)*w,y,Math.sin(a)*w);
   dummy.quaternion.setFromUnitVectors(YAXIS,dir);
   dummy.scale.setScalar(.75+r()*.7);
   dummy.position.copy(pos).addScaledVector(dir,.007*dummy.scale.x);dummy.updateMatrix();stalks.setMatrixAt(i,dummy.matrix);
   dummy.position.copy(pos).addScaledVector(dir,.018*dummy.scale.x);dummy.updateMatrix();heads.setMatrixAt(i,dummy.matrix);
  }

  const sugar=mat(new T.Color(v.leaf).lerp(new T.Color('#dfe7c4'),.16).getStyle(),{side:T.DoubleSide,map:surface,roughness:.88});
  for(let i=0;i<leaves;i++){
   const t=.12+r()*.82,a=r()*6.283,w=.12+cola(t)*.2;
   const l=mesh(sugarGeo(),sugar,g,Math.cos(a)*w,t*H,Math.sin(a)*w);
   l.rotation.set(.45+r()*.35,a,-.45+r()*.9);l.scale.setScalar(.38+r()*.38);l.receiveShadow=false;
  }
  lobes.castShadow=true;hairs.castShadow=false;stalks.castShadow=false;heads.castShadow=false;
  g.add(lobes,hairs,stalks,heads);
  g.userData.sparkle=heads.material;g.userData.detail=detail;
  g.scale.setScalar(size);return g;
 }
 function seed(v){const g=new T.Group(),r=rnd(v.seed);const body=mesh(new T.SphereGeometry(.29,16,12),mat('#9e7751'),g);body.scale.set(.8,1.3,.7);for(let i=0;i<8;i++){const line=mesh(new T.TorusGeometry(.22+r()*.04,.014,4,20,Math.PI*1.15),mat('#493827'),g);line.rotation.set(r()*1.4,r()*3,r()*1.5);line.position.y=(r()-.5)*.3;}g.rotation.z=-.3;return g;}
 function leafGeo(){const s=new T.Shape();s.moveTo(0,0);for(let i=1;i<=8;i++){const y=i/9,w=Math.sin(y*Math.PI)*.14;s.lineTo(w*(i%2?.72:1.18),y);}s.lineTo(0,1);for(let i=8;i>=1;i--){const y=i/9,w=Math.sin(y*Math.PI)*.14;s.lineTo(-w*(i%2?.72:1.18),y);}s.closePath();return new T.ShapeGeometry(s);}
 function plant(v,progress=1){
  const g=new T.Group(),r=rnd(v.seed),leaf=mat(v.leaf,{side:T.DoubleSide}),stem=mat('#6a863d'),lg=leafGeo();
  if(progress<.04){const s=seed(v);s.scale.setScalar(.24);s.position.y=.035;g.add(s);return g;}
  const height=v.height*(.28+.72*progress)*1.3;
  tube(g,new T.Vector3(),new T.Vector3(0,height,0),.027,stem);
  const tiers=progress<.22?2:progress<.5?3:5;
  for(let level=0;level<tiers;level++)for(let side=0;side<2;side++){
   const angle=level*2.1+side*Math.PI+r()*.15,y=height*(.2+level*.145),reach=(.38-level*.028)*v.width*(.5+progress*.5);
   const tip=new T.Vector3(Math.cos(angle)*reach,y+.16,Math.sin(angle)*reach);
   tube(g,new T.Vector3(0,y,0),tip,.012,stem);
   const fan=new T.Group();fan.position.copy(tip);fan.rotation.set(-.55,angle-Math.PI/2,.06);g.add(fan);
   const blades=progress<.2?3:7;
   for(let k=0;k<blades;k++){
    const center=(blades-1)/2,spread=(k-center)*.34,len=(.46-Math.abs(k-center)*.055)*(.55+progress*.5);
    const l=mesh(lg,leaf,fan);l.scale.set(v.width*len*1.6,len,1);l.rotation.z=spread;l.rotation.x=.1+Math.abs(k-center)*.09;
   }
   if(progress>.58&&level>1&&side===0){const b=bud({...v,seed:v.seed+level},.12+(progress-.58)*.3);b.position.copy(tip);g.add(b);}
  }
  if(progress>.45){const b=bud(v,.11+(progress-.45)*.36);b.position.y=height-.05;g.add(b);}
  return g;
 }
 function pot(index,soil=true){const g=new T.Group(),terracotta=mat(index%2?'#9b654a':'#bc7c54');
  mesh(new T.CylinderGeometry(.47,.33,.6,24,1,true),terracotta,g,0,.3);const rim=mesh(new T.TorusGeometry(.46,.04,8,32),terracotta,g,0,.61);rim.rotation.x=Math.PI/2;
  mesh(new T.CylinderGeometry(.325,.325,.03,24),terracotta,g,0,.035);
  if(soil){mesh(new T.CylinderGeometry(.435,.425,.05,24),mat('#3b2e20'),g,0,.56);const r=rnd(index+31),pebble=mat('#64503a');for(let i=0;i<12;i++){const a=r()*6.28,d=r()*.36;mesh(new T.IcosahedronGeometry(.025,0),pebble,g,Math.cos(a)*d,.595,Math.sin(a)*d);}}
  const tag=box(g,.18,.12,.015,mat('#e8dec0'),0,.36,.416);tag.rotation.x=-.15;
  return g;
 }
 function wateringCan(){const g=new T.Group(),m=mat('#78a295',{metalness:.3,roughness:.35});mesh(new T.CylinderGeometry(.22,.25,.4,20),m,g,0,.2);tube(g,new T.Vector3(.15,.2,0),new T.Vector3(.65,.43,0),.055,m);const handle=mesh(new T.TorusGeometry(.21,.035,7,24,Math.PI*1.8),m,g,-.22,.3);handle.rotation.z=.35;const lid=mesh(new T.CylinderGeometry(.1,.1,.02,16),mat('#263f33'),g,0,.41);return g;}
 function scissors(){const g=new T.Group(),metal=mat('#c8d2cb',{metalness:.85,roughness:.2}),handle=mat('#d7a057');g.userData.blades=[];for(const side of [-1,1]){const part=new T.Group();g.add(part);part.rotation.z=side*.22;const ring=mesh(new T.TorusGeometry(.095,.025,6,20),handle,part,side*.06,-.2);const blade=box(part,.036,.4,.025,metal,side*.012,.15);blade.rotation.z=side*.03;g.userData.blades.push(part);}mesh(new T.SphereGeometry(.037,8,6),metal,g);g.rotation.x=-Math.PI/2;return g;}
 function shovel(){const g=new T.Group(),wood=mat('#a98250'),iron=mat('#83928b',{metalness:.65});tube(g,new T.Vector3(0,0,0),new T.Vector3(0,.5,0),.04,wood);const head=mesh(new T.SphereGeometry(.13,12,8),iron,g,0,-.13);head.scale.set(1,1.5,.15);return g;}
 function sack(){const g=new T.Group(),m=mat('#c7b68c');const o=box(g,.42,.48,.26,m,0,.24);o.rotation.z=.08;box(g,.3,.015,.29,mat('#9b8c64'),0,.48);box(g,.25,.18,.005,mat('#5c7750'),0,.25,.135);return g;}
 function jar(v){const g=new T.Group(),glass=mat('#b3d0c4',{transparent:true,opacity:.24,roughness:.1,depthWrite:false}),lid=mat('#d2b779',{metalness:.5});mesh(new T.CylinderGeometry(.15,.15,.32,16,1,true),glass,g,0,.18);mesh(new T.CylinderGeometry(.16,.16,.045,16),lid,g,0,.36);if(v){const b=bud(v,.16);b.position.y=.07;g.add(b);}return g;}
 function flask(){const g=new T.Group(),glass=mat('#b4dfd5',{transparent:true,opacity:.45,roughness:.1}),liquid=mat('#b786d8',{emissive:'#583c70',emissiveIntensity:.3});mesh(new T.SphereGeometry(.15,14,10),glass,g,0,.15);mesh(new T.CylinderGeometry(.046,.046,.22,12,1,true),glass,g,0,.36);mesh(new T.SphereGeometry(.12,12,8),liquid,g,0,.13);return g;}
 function fan(){const g=new T.Group(),m=mat('#69887c',{metalness:.5});box(g,.38,.06,.25,m,0,.04);tube(g,new T.Vector3(0,.06,0),new T.Vector3(0,.47,0),.035,m);const cage=mesh(new T.TorusGeometry(.24,.025,7,32),m,g,0,.65);const blades=new T.Group();blades.position.y=.65;g.add(blades);for(let i=0;i<4;i++){const b=mesh(new T.SphereGeometry(.1,8,6),mat('#b0b6a0'),blades);b.position.set(Math.cos(i*Math.PI/2)*.11,Math.sin(i*Math.PI/2)*.11,0);b.scale.set(.75,1.6,.2);b.rotation.z=i*Math.PI/2;}g.userData.rotor=blades;return g;}
 function humidifier(){const g=new T.Group(),m=mat('#d2ccb5');mesh(new T.CylinderGeometry(.14,.19,.35,18),m,g,0,.2);mesh(new T.TorusGeometry(.08,.017,6,20),mat('#79c5b1',{emissive:'#2b7b68',emissiveIntensity:.5}),g,0,.38).rotation.x=Math.PI/2;const mist=mesh(new T.SphereGeometry(.12,10,8),mat('#d1e4e1',{transparent:true,opacity:.18,depthWrite:false}),g,0,.58);mist.scale.set(.8,1.8,.8);g.userData.mist=mist;return g;}
 function loupe(){const g=new T.Group(),m=mat('#c4ac73',{metalness:.7});mesh(new T.TorusGeometry(.18,.023,8,26),m,g);mesh(new T.CircleGeometry(.16,24),mat('#a8d9d0',{transparent:true,opacity:.25,side:T.DoubleSide}),g);tube(g,new T.Vector3(0,-.18,0),new T.Vector3(0,-.48,0),.036,m);g.rotation.x=-Math.PI/2;return g;}
 function dispose(group){const gs=new Set(),ms=new Set();group.traverse(o=>{if(o.geometry&&!o.geometry.userData.shared)gs.add(o.geometry);if(o.material)(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>{if(!m.userData.shared)ms.add(m);});});gs.forEach(g=>g.dispose());ms.forEach(m=>m.dispose());}
 function studio(element,onSelect){
  const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches,scene=new T.Scene();scene.background=new T.Color('#303e2d');scene.fog=new T.Fog('#303e2d',12,28);
  const renderer=new T.WebGLRenderer({antialias:true,alpha:false});renderer.setPixelRatio(Math.min(devicePixelRatio||1,1.5));renderer.outputEncoding=T.sRGBEncoding;renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=1.08;renderer.shadowMap.enabled=true;renderer.shadowMap.type=T.PCFSoftShadowMap;renderer.setClearColor('#303e2d');element.append(renderer.domElement);
  const camera=new T.PerspectiveCamera(38,1,.1,40);let angle=.15,elevation=4.8,distance=8.6,goalElevation=4.8,goalDistance=8.6,lookHeight=1,auto=false,lit=true,running=true,disposed=false,capture=null;
  const MIN_ZOOM=4.6,MAX_ZOOM=12.5;
  const ambient=new T.HemisphereLight('#edf1d8','#324332',.95);scene.add(ambient);
  const sun=new T.DirectionalLight('#fff2ce',1.55);sun.position.set(-3,7,4);sun.castShadow=true;sun.shadow.mapSize.set(1024,1024);sun.shadow.camera.left=-6;sun.shadow.camera.right=6;sun.shadow.camera.top=6;sun.shadow.camera.bottom=-6;sun.shadow.normalBias=.035;scene.add(sun);
  const fill=new T.DirectionalLight('#aecdae',.55);fill.position.set(4,3,-3);scene.add(fill);
  const rim=new T.DirectionalLight('#cfe6ff',.42);rim.position.set(1.5,2.4,-6);scene.add(rim);
  const lampLight=new T.PointLight('#f6ffcf',.85,9,2);lampLight.position.set(0,3.05,-.15);scene.add(lampLight);
  const fixed=new T.Group();scene.add(fixed);
  const ground=mesh(new T.PlaneGeometry(100,100),mat('#354732'),fixed,0,-.6);ground.rotation.x=-Math.PI/2;
  box(fixed,5.65,.18,3.55,mat('#9f8253'),0,-.12);box(fixed,5.45,.12,3.4,mat('#bc9b64'),0,-.02);
  for(const x of [-2.55,2.55])for(const z of [-1.45,1.45])box(fixed,.13,.6,.13,mat('#61513a'),x,-.36,z);
  const frameMat=mat('#69816a',{metalness:.4});for(const x of [-2.85,2.85]){box(fixed,.055,3.5,.055,frameMat,x,1.2,-1.8);tube(fixed,new T.Vector3(x,2.95,-1.8),new T.Vector3(0,3.8,-1.8),.035,frameMat);}box(fixed,5.7,.055,.055,frameMat,0,2.95,-1.8);
  const lamp=box(fixed,1.65,.08,.32,mat('#bcc1a4',{metalness:.5}),0,3.25,-.15);const glow=box(fixed,1.5,.025,.25,mat('#edf5b8',{emissive:'#e7f4a7',emissiveIntensity:1}),0,3.19,-.15);
  for(const x of [-.65,.65])tube(fixed,new T.Vector3(x,3.3,-.15),new T.Vector3(x,3.8,-1.8),.012,frameMat);
  const can=wateringCan();can.position.set(2.24,.08,.98);can.rotation.y=-.5;fixed.add(can);
  const shears=scissors();shears.position.set(1.55,.12,1.33);fixed.add(shears);
  const spade=shovel();spade.rotation.set(-Math.PI/2,0,-.6);spade.position.set(-2.2,.14,1.1);fixed.add(spade);
  const bag=sack();bag.position.set(-2.35,.08,-1.1);fixed.add(bag);
  const vial=flask();vial.position.set(2.4,.08,-1.15);fixed.add(vial);
  let pots=new T.Group();scene.add(pots);const anchors=[[-1.55,.05,.62],[0,.05,.62],[1.55,.05,.62],[-1.55,.05,-.85],[0,.05,-.85],[1.55,.05,-.85]];
  let selected=0,lastKey='',targets=[],animation=null,particles=[],inspecting=false,inspection=null,readyPots=[];
  const extras=new T.Group();fixed.add(extras);let extraKey='';const clock=new T.Clock();
  const ring=mesh(new T.TorusGeometry(.57,.018,5,40),mat('#e0ee9d',{emissive:'#8eaa42',emissiveIntensity:.6}),scene);ring.rotation.x=Math.PI/2;
  function sync(state,catalog,now){
   const accessories=JSON.stringify(state.accessories||{});if(accessories!==extraKey){extraKey=accessories;while(extras.children.length){const child=extras.children[0];extras.remove(child);dispose(child);}if(state.accessories?.fan){const o=fan();o.position.set(2.4,.09,-.45);extras.add(o);}if(state.accessories?.humidifier){const o=humidifier();o.position.set(-2.35,.1,-.25);extras.add(o);}if(state.accessories?.loupe){const o=loupe();o.position.set(.6,.13,1.44);extras.add(o);}}
   readyPots=state.pots.map(p=>!!(p.plant&&p.plant.ready!==null&&now>=p.plant.ready));
   if(inspecting)return;
   const key=JSON.stringify(state.pots.map(p=>[p.soil,p.plant?.id,p.plant?.fed,p.plant?.ready?Math.min(10,Math.floor((1-(p.plant.ready-now)/catalog[p.plant.id].duration)*10)):0]));
   if(key===lastKey)return;lastKey=key;scene.remove(pots);dispose(pots);pots=new T.Group();scene.add(pots);targets=[];
   state.pots.forEach((p,i)=>{const group=pot(i,p.soil);group.position.set(...anchors[i]);group.userData.pot=i;pots.add(group);targets.push(group);
    if(p.plant){const v=catalog[p.plant.id],progress=p.plant.started===null?0:Math.min(1,Math.max(.06,1-(p.plant.ready-now)/v.duration));const pl=plant(v,progress);pl.position.y=.6;pl.userData.sway=true;group.add(pl);
     if(p.plant.ready!==null&&now>=p.plant.ready){const halo=mesh(new T.TorusGeometry(.34,.014,8,30),mat('#f0f7bb',{emissive:'#cfe486',emissiveIntensity:.9}),group,0,.78+1.3*v.height,0);halo.rotation.x=Math.PI/2;halo.castShadow=false;halo.receiveShadow=false;halo.userData.pulse='ring';}
     if(p.plant.fed){const star=mesh(new T.OctahedronGeometry(.055,0),mat('#ffe9a8',{emissive:'#c9a742',emissiveIntensity:.7}),group,.3,.9+1.3*v.height,0);star.castShadow=false;star.userData.pulse='star';}}
   });
   for(let i=0;i<3;i++){const id=Object.keys(state.buds||{})[i];if(id){const j=jar(catalog[id]);j.position.set(-.65+i*.4,.07,1.48);pots.add(j);}}
  }
  function select(index){selected=index;ring.position.set(anchors[index][0],.085,anchors[index][2]);}
  const ray=new T.Raycaster(),mouse=new T.Vector2();let down=null;
  const onDown=e=>{if(e.target!==renderer.domElement)return;down={x:e.clientX,y:e.clientY,last:e.clientX,lastY:e.clientY,moved:false};};
  const onMove=e=>{if(!down)return;const d=e.clientX-down.last,dy=e.clientY-down.lastY;down.last=e.clientX;down.lastY=e.clientY;
   if(Math.abs(e.clientX-down.x)>5||Math.abs(e.clientY-down.y)>5)down.moved=true;
   if(down.moved){angle-=d*.007;goalElevation=Math.max(1.1,Math.min(8.2,goalElevation+dy*.02));}};
  const onWheel=e=>{e.preventDefault();goalDistance=Math.max(MIN_ZOOM,Math.min(MAX_ZOOM,goalDistance+Math.sign(e.deltaY)*.6));};
  let pinch=0;
  const onTouch=e=>{if(e.touches.length!==2){pinch=0;return;}const dx=e.touches[0].clientX-e.touches[1].clientX,dy=e.touches[0].clientY-e.touches[1].clientY,d=Math.hypot(dx,dy);
   if(pinch)goalDistance=Math.max(MIN_ZOOM,Math.min(MAX_ZOOM,goalDistance+(pinch-d)*.02));pinch=d;};
  const onUp=e=>{if(!down)return;const d=down;down=null;if(d.moved)return;const bounds=renderer.domElement.getBoundingClientRect();mouse.set((e.clientX-bounds.left)/bounds.width*2-1,-(e.clientY-bounds.top)/bounds.height*2+1);ray.setFromCamera(mouse,camera);const hits=ray.intersectObjects(targets,true);if(hits[0]){let o=hits[0].object;while(o&&o.userData.pot===undefined)o=o.parent;if(o)onSelect(o.userData.pot);}};
  renderer.domElement.addEventListener('pointerdown',onDown);window.addEventListener('pointermove',onMove);window.addEventListener('pointerup',onUp);
  renderer.domElement.addEventListener('wheel',onWheel,{passive:false});renderer.domElement.addEventListener('touchmove',onTouch,{passive:true});renderer.domElement.addEventListener('touchend',()=>{pinch=0;});renderer.domElement.style.cursor='grab';
  function effect(type,index){if(reduced)return;const target=new T.Vector3(...anchors[index]);target.y=1.15;animation={type,target,start:clock.getElapsedTime(),canPos:can.position.clone(),canRot:can.rotation.clone(),shearPos:shears.position.clone()};const color=type==='water'?'#a6ddec':type==='soil'?'#6d4e2c':type==='cross'?'#d6a1eb':type==='feed'?'#ffe6a2':type==='harvest'?'#cfe38d':'#ddec91';
   const count=type==='harvest'||type==='cross'?34:24;
   for(let i=0;i<count;i++){const dot=mesh(new T.IcosahedronGeometry(type==='water'?.025:.04,0),mat(color,{emissive:color,emissiveIntensity:.18}),scene);dot.position.copy(target);dot.position.x+=(Math.random()-.5)*.5;dot.position.z+=(Math.random()-.5)*.5;dot.castShadow=false;dot.receiveShadow=false;particles.push({mesh:dot,birth:clock.getElapsedTime(),spin:(Math.random()-.5)*4,velocity:new T.Vector3((Math.random()-.5)*.75,type==='water'?-1.25:Math.random()*1.9+.35,(Math.random()-.5)*.75)});}
  }
  function resize(){const w=element.clientWidth||600,h=element.clientHeight||420;renderer.setSize(w,h,false);camera.aspect=w/h;camera.updateProjectionMatrix();}
  const observer=new ResizeObserver(resize);observer.observe(element);resize();
  renderer.domElement.addEventListener('webglcontextlost',e=>{e.preventDefault();running=false;element.dispatchEvent(new CustomEvent('gp-context-lost',{bubbles:true}));});
  function frame(){if(disposed)return;requestAnimationFrame(frame);if(!running||document.hidden)return;const t=clock.getElapsedTime();if(auto&&!reduced)angle+=.002;
   const ease=reduced?1:.12;distance+=(goalDistance-distance)*ease;elevation+=(goalElevation-elevation)*ease;lookHeight+=((inspecting?.7:1)-lookHeight)*ease;
   camera.position.set(Math.sin(angle)*distance,elevation,Math.cos(angle)*distance);camera.lookAt(0,lookHeight,0);
   if(!reduced){const beat=.5+Math.sin(t*2.6)*.5;pots.traverse(o=>{if(o.userData.pulse){if(o.userData.pulse==='ring')o.rotation.z=t*.9;else o.rotation.set(t*.7,t*1.1,0);o.scale.setScalar(1+beat*.14);if(o.material.emissiveIntensity!==undefined)o.material.emissiveIntensity=.45+beat*.75;}});
    ring.material.emissiveIntensity=(readyPots[selected]?.5+beat*.9:.6);}
   if(!reduced)extras.children.forEach(o=>{if(o.userData.rotor)o.userData.rotor.rotation.z=t*5;if(o.userData.mist){o.userData.mist.position.y=.6+Math.sin(t)*.04;o.userData.mist.material.opacity=.15+Math.sin(t*.8)*.05;}});
   if(inspection&&!reduced)inspection.rotation.y=t*.16;
   if(!reduced)pots.traverse(o=>{if(o.userData.sway)o.rotation.z=Math.sin(t*1.4+o.parent.position.x)*.016;});
   if(animation){const a=animation,q=Math.min(1,(t-a.start)/1.15);if(a.type==='water'){can.position.lerpVectors(a.canPos,a.target.clone().add(new T.Vector3(-.55,.3,0)),Math.sin(q*Math.PI));can.rotation.z=-Math.sin(q*Math.PI)*.6;}if(a.type==='harvest'){shears.position.lerpVectors(a.shearPos,a.target,Math.sin(q*Math.PI));shears.userData.blades.forEach((b,i)=>b.rotation.z=(i?1:-1)*(.07+Math.abs(Math.sin(q*15))*.3));}if(q>=1){can.position.copy(a.canPos);can.rotation.copy(a.canRot);shears.position.copy(a.shearPos);animation=null;}}
   particles=particles.filter(p=>{const age=t-p.birth;if(age>1.4){scene.remove(p.mesh);dispose(p.mesh);return false;}p.mesh.position.addScaledVector(p.velocity,.016);p.velocity.y-=.025;p.mesh.rotation.y+=p.spin*.016;p.mesh.scale.setScalar(Math.max(.02,1-age/1.4));return true;});renderer.render(scene,camera);
   if(capture){const shot=capture;capture=null;shot(renderer.domElement.toDataURL('image/png'));}
  }select(0);frame();
  return {sync,select,effect,
   inspect(v){
    inspecting=!inspecting;fixed.visible=pots.visible=ring.visible=!inspecting;
    if(inspecting){
     inspection=new T.Group();const b=bud(v);b.position.y=-.15;inspection.add(b);
     const stand=mesh(new T.CylinderGeometry(.42,.5,.06,28),mat('#22301f',{roughness:.7}),inspection,0,-.19);stand.receiveShadow=true;
     scene.add(inspection);goalDistance=3.4;goalElevation=1.4;
    }else{scene.remove(inspection);dispose(inspection);inspection=null;goalDistance=8.6;goalElevation=4.8;lastKey='';}
    return inspecting;},
   zoom(delta){goalDistance=Math.max(MIN_ZOOM,Math.min(MAX_ZOOM,goalDistance+delta));return goalDistance;},
   snapshot(){return new Promise(resolve=>{capture=resolve;});},
   rotate(){auto=!auto;return auto;},
   light(){lit=!lit;sun.intensity=lit?1.55:.35;rim.intensity=lit?.42:.2;lampLight.intensity=lit?.85:.05;ambient.intensity=lit?.95:.5;glow.material.emissiveIntensity=lit?1:0;return lit;},
   dispose(){disposed=true;observer.disconnect();window.removeEventListener('pointermove',onMove);window.removeEventListener('pointerup',onUp);renderer.dispose();dispose(scene);}};
 }
 root.GPModels={bud,seed,plant,pot,wateringCan,scissors,shovel,sack,jar,flask,studio,dispose,fan,humidifier,loupe};
})(typeof window==='undefined'?globalThis:window);
