<input type="text" id="search">

<div id="results"></div>

<script>
document.getElementById("search").addEventListener("input", async function(){

let q=this.value;

if(q.length<3) return;

let res=await fetch("/search?q="+q);

let games=await res.json();

let html="";

games.forEach(g=>{
html+=`
<div>
<img src="${g.thumb}" width="100">
${g.external} - $${g.cheapest}
</div>
`;
});

document.getElementById("results").innerHTML=html;

});
</script>