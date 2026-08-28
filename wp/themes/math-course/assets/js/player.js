document.addEventListener(
'DOMContentLoaded',
function(){


const players =
document.querySelectorAll(
'.mathcourse-player'
);


players.forEach(
function(player){

player.controls=true;

}
);


}
);