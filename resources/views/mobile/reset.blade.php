<!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer">
<title>Recuperar acceso · RSU</title>
<style>body{margin:0;background:#f4f6f0;color:#17342c;font:1rem system-ui}main{max-width:25rem;background:white;margin:8vh auto;padding:2rem;border-radius:1rem}label,input,button{display:block}input,button{width:100%;box-sizing:border-box;padding:.8rem;margin:.5rem 0 1rem;border:1px solid #aac1b3;border-radius:.5rem}button{background:#126453;color:white}h1{font-size:1.5rem}</style>
<main><h1>Nueva contraseña de ciudadano</h1><form id="form"><label>Contraseña (mínimo 8 caracteres)<input id="pass" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></label><label>Repite la contraseña<input id="confirmPass" type="password" required autocomplete="new-password"></label><button>Guardar contraseña</button></form><p id="message" role="status"></p></main>
<script>
const token=location.hash.slice(1), form=document.getElementById('form'), message=document.getElementById('message');
history.replaceState(null,'',location.pathname);
if(!token){form.hidden=true;message.textContent='Abre el enlace completo del correo.'}
form.onsubmit=async event=>{
 event.preventDefault();
 const password=document.getElementById('pass').value, confirmation=document.getElementById('confirmPass').value;
 if(password!==confirmation){message.textContent='Las contraseñas deben coincidir.';return}
 const button=form.querySelector('button');button.disabled=true;
 try{
   const response=await fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({token,password,password_confirmation:confirmation})});
   const data=await response.json(); message.textContent=data.message;
   if(response.ok){form.reset();form.hidden=true}
 }catch{message.textContent='No se pudo conectar. Inténtalo nuevamente.'}finally{button.disabled=false}
};
</script></html>
