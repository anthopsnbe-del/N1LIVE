/* 1 000 missions de fan : jalons narratifs et opérations secondaires. */
(function(){
  var arcs=[
    ['La chute de Shiganshina','845','colossal','Évacuer les habitants avant la chute du mur Maria.',['La porte extérieure','Les rues envahies','Le dernier convoi','Le mur Maria tombe']],
    ['Le 104e bataillon','847–850','soldat','Apprendre à manier les lames et le dispositif tridimensionnel.',['Le camp des recrues','Équilibre et propulsion','Exercice en forêt','La remise des insignes']],
    ['La bataille de Trost','850','titan','Rejoindre le quartier général et protéger la retraite.',['La brèche de Trost','Le dépôt de gaz','Le rocher d’Eren','La porte est scellée']],
    ['Le Titan Féminin','850','feminin','Accompagner l’expédition puis participer à la traque d’Annie.',['La formation longue distance','La forêt géante','Le retour du bataillon','Le piège de Stohess']],
    ['Le choc des Titans','850','blinde','Explorer le mur Rose et secourir les soldats isolés.',['Le village de Ragako','La nuit à Utgard','La révélation des guerriers','Le sauvetage d’Eren']],
    ['Le soulèvement','850','soldat','Protéger Historia et découvrir les secrets du pouvoir royal.',['Les rues de la capitale','La traque de Kenny','La chapelle des Reiss','Le couronnement d’Historia']],
    ['Le retour à Shiganshina','850','bestial','Reprendre le mur Maria et accéder au sous-sol des Jäger.',['Le scellement de la porte','Le siège du Bestial','La charge du bataillon','Les carnets du sous-sol']],
    ['De la mer à Liberio','851–854','blinde','Découvrir le monde extérieur puis suivre l’opération de Liberio.',['Au-delà des murs','L’horizon maritime','Les rues de Liberio','Le retrait en dirigeable']],
    ['La guerre pour Paradis','854','blinde','Traverser les divisions de Paradis jusqu’à la rupture des murs.',['Les tensions sur l’île','Le coup des Jägerists','L’assaut sur Shiganshina','Le réveil du Grondement']],
    ['L’Alliance et le dernier combat','854','colossal','Réunir l’Alliance et atteindre Eren pour arrêter le Grondement.',['La naissance de l’Alliance','La bataille du port','Le départ d’Odiha','La fin du pouvoir des Titans']]
  ];
  var missions=['Reconnaître le secteur','Escorter les renforts','Sécuriser les réserves','Ouvrir un passage','Protéger le repli'];
  var encounters=[
    [['Titan errant','titan'],['Titan anormal','anormal'],['Titan errant','titan'],['Titan Blindé','blinde']],
    [['Cible fixe','soldat'],['Cible mobile','soldat'],['Parcours de forêt','soldat'],['Épreuve finale','soldat']],
    [['Titan errant','titan'],['Titan anormal','anormal'],['Titan de grande taille','titan'],['Dernier Titan du secteur','anormal']],
    [['Titan anormal','anormal'],['Titan Féminin','feminin'],['Titan poursuivant','titan'],['Annie — Titan Féminin','feminin']],
    [['Titan de Ragako','titan'],['Titan assiégeant Utgard','anormal'],['Reiner — Titan Blindé','blinde'],['Titan encerclant le convoi','titan']],
    [['Patrouille intérieure','soldat'],['Unité de Kenny','soldat'],['Titan de Rod Reiss','rampant'],['Épreuve de protection','soldat']],
    [['Reiner — Titan Blindé','blinde'],['Sieg — Titan Bestial','bestial'],['Bertolt — Titan Colossal','colossal'],['Dernier Titan des ruines','titan']],
    [['Titan des plaines','titan'],['Titan côtier','rampant'],['Garde de Liberio','soldat'],['Reiner — Titan Blindé','blinde']],
    [['Unité d’entraînement','soldat'],['Patrouille Jägerist','soldat'],['Reiner — Titan Blindé','blinde'],['Titan transformé de Shiganshina','titan']],
    [['Titan du Grondement','colossal'],['Unité Jägerist du port','soldat'],['Titan Colossal en marche','colossal'],['Eren — dernier affrontement','titan']]
  ];
  var list=[];
  arcs.forEach(function(a,ai){for(var j=0;j<100;j++){
    var phase=Math.floor(j/25), milestone=j%25===24, training=ai===1, human=ai===5;
    var mob=training?['Cible d’entraînement','soldat']:human?['Brigade intérieure','soldat']:['Titan errant','titan'];
    /* Le titre du chapitre est celui de la mission, pas celui de l'arc :
       cent chapitres nommés « Le 104e bataillon » donnaient une campagne
       qui semblait vide. L'arc reste affiché à côté du numéro. */
    var title=milestone?a[4][phase]:missions[j%5];
    list.push({name:title,arcName:a[0],arc:ai,year:a[1],milestone:milestone,
      brief:(milestone?'Jalon : '+a[4][phase]+'. ':'Mission secondaire '+(j+1)+' : '+missions[j%5]+'. ')+a[3],
      pal:'chair',fights:milestone?40:25+((ai*100+j)%16),sky:['#31433f','#111b22'],mobs:[mob],
      boss:milestone?encounters[ai][phase].concat('chair'):[training?'Parcours chronométré':human?'Chef de patrouille':'Titan anormal',training||human?'soldat':'anormal','chair']});
  }});
  window.Content.CHAPTERS=list;window.Content.ARCS=arcs;
})();

