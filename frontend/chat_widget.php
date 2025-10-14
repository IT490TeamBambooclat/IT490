<?php
// Only show chat if user is logged in
if (isset($_SESSION['username']) && isset($_SESSION['session_id'])):
?>
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/68ee7b8e17b521195123438e/1j7hp58vi';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

<!-- Custom Floating Chat Prompt -->
<style>
#chat-prompt {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background-color: #004080;
  color: white;
  border-radius: 30px;
  padding: 10px 20px;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
  font-weight: bold;
  font-size: 14px;
  z-index: 9999;
  transition: background-color 0.3s ease;
}
#chat-prompt:hover {
  background-color: #0066cc;
}
</style>

<div id="chat-prompt" onclick="Tawk_API.maximize();">💬 Need Help? Chat here</div>
<?php endif; ?>
