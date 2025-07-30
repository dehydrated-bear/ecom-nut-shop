document.addEventListener("DOMContentLoaded",function(){
    let navOpen=false;

console.log(1);

document.getElementById("nav-button").onclick = function(){
    console.log(2);
    if(!navOpen){
        document.getElementById("navbarCart").style.width="380px";
        console.log(3);
        navOpen=true;
    }

    else if(navOpen){
        document.getElementById("navbarCart").style.width="0px"
        navOpen=false


    }
    

    };

});


document.addEventListener("DOMContentLoaded", function () {
  const searchBtn = document.getElementById("search-button");
  const searchInput = document.getElementById("search-input");

  let searchVisible = false;

  searchBtn.addEventListener("click", function () {
    searchVisible = !searchVisible;
    searchInput.style.display = searchVisible ? "block" : "none";
    if (searchVisible) {
      searchInput.focus();
    }
  });
});
