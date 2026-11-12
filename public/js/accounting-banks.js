(() => {
 const rows=document.getElementById('bank-rows'), add=document.getElementById('bank-add');
 if(!rows || !add)return;
 let index=Math.max(-1,...Array.from(rows.querySelectorAll('input[name]'),el=>Number(el.name.match(/\[(\d+)\]/)[1])))+1;
 add.addEventListener('click',()=>{
  if(rows.children.length>=10)return;
  const fragment=document.getElementById('bank-template').content.cloneNode(true);
  fragment.querySelectorAll('[data-field]').forEach(input=>input.name=`bank_accounts[${index}][${input.dataset.field}]`);
  index++;rows.append(fragment);rows.lastElementChild.querySelector('input').focus();
 });
 rows.addEventListener('click',event=>{const button=event.target.closest('.bank-remove');if(button)button.closest('.bank-row').remove();});
})();
