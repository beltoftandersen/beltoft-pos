/* Till product list - quantity steppers and variation-row add, as on the B2B
   list. Keeps each row's add button carrying the typed quantity. */
(function(){
	var i18n=(window.beltoftPosList&&window.beltoftPosList.i18n)||{onlyInStock:'Only %d in stock.',couldNotAddCap:'Could not add. Only %d in stock.',couldNotAdd:'Could not add this line.'};
	function sync(input){
		var row=input.closest('.brxe-product-add-to-cart');
		if(!row)return;
		var button=row.querySelector('a.add_to_cart_button,a.button');
		if(!button)return;
		var value=parseInt(input.value,10);
		button.dataset.quantity=(isNaN(value)||value<1)?1:value;
	}
	/*
	 * Tell the buyer at the moment they type it, not after a round trip that
	 * comes back refused. The cap is already on the input as `max`, taken from
	 * the line's own stock, so this needs no request.
	 */
	function note(input){
		var row=input.closest('.brxe-product-add-to-cart')||input.closest('.beltoft-loop-variable');
		if(!row)return;
		var max=parseInt(input.max,10);
		var val=parseInt(input.value,10)||0;
		var host=input.closest('.beltoft-loop-variable')||row;
		var el=host.querySelector('.beltoft-loop-note');
		if(isNaN(max)||val<=max){ if(el)el.remove(); return; }
		if(!el){ el=document.createElement('span'); el.className='beltoft-loop-note'; el.setAttribute('role','status'); host.appendChild(el); }
		el.textContent=i18n.onlyInStock.replace('%d',max);
	}
	document.addEventListener('input',function(e){
		var input=e.target.closest('.beltoft-loop-qty__input');
		if(input){sync(input);note(input);}
	});
	/*
	 * Variable products add through Woo's own AJAX endpoint with the chosen
	 * variation_id. Woo's loop script only knows how to add a simple product,
	 * so this posts the same shape it would and lets the same fragment refresh
	 * update the header and the cart panel - no second cart mechanism.
	 */
	document.addEventListener('click',function(e){
		var add=e.target.closest('.beltoft-loop-variable__add');
		if(!add)return;
		e.preventDefault();
		var wrap=add.closest('.beltoft-loop-variable');
		var pick=wrap&&wrap.querySelector('.beltoft-loop-variation');
		var qty=wrap&&wrap.querySelector('.beltoft-loop-qty__input');
		if(!wrap||!pick)return;
		/* One add at a time per row: a double tap must not add twice. */
		if(add.classList.contains('is-busy'))return;
		/*
		 * The VARIATION id goes in as product_id. WC_AJAX::add_to_cart ignores
		 * a variation_id field entirely and instead detects that the posted
		 * product is a variation, deriving the parent itself
		 * (class-wc-ajax.php:525). Posting parent + variation_id looks right
		 * and fails with Woo's "go to the product page" response.
		 */
		var body=new URLSearchParams();
		body.append('product_id',pick.value);
		body.append('quantity',(parseInt(qty&&qty.value,10)||1));
		var label=add.textContent;
		/* Bricks restores the label it saved on `adding_to_cart` - an event this
		   path never fires - so it put back nothing and left the button blank. */
		if(!add.hasAttribute('data-original-text'))add.setAttribute('data-original-text',add.innerHTML);
		add.classList.add('is-busy');
		add.setAttribute('aria-busy','true');
		function fail(cap){
			var el=wrap.querySelector('.beltoft-loop-note');
			if(!el){el=document.createElement('span');el.className='beltoft-loop-note';el.setAttribute('role','status');wrap.appendChild(el);}
			el.textContent=cap?i18n.couldNotAddCap.replace('%d',cap):i18n.couldNotAdd;
			add.classList.remove('is-busy');
			add.removeAttribute('aria-busy');
		}
		fetch('/?wc-ajax=add_to_cart',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
			.then(function(r){return r.json();})
			.then(function(data){
				/*
				 * WC_AJAX::add_to_cart answers 200 with {error:true} whenever
				 * validation fails or the cart refuses the line - out of stock,
				 * an enforcement guard, an unpublished product. Treating any
				 * 200 as success showed a tick for an item that was never
				 * added, which is the worst possible outcome on an order form:
				 * the buyer believes the line is in and finds out at checkout.
				 */
				if(!data||data.error||!data.fragments){
					/*
					 * WC_AJAX answers 200 with {error:true} for every refusal -
					 * out of stock, an enforcement guard, an unpublished
					 * product - and carries no reason. Say what is knowable
					 * (the stock cap, which is on the input) rather than a bare
					 * cross the buyer cannot act on.
					 */
					fail(qty&&parseInt(qty.max,10));
					return;
				}
				var cleared=wrap.querySelector('.beltoft-loop-note');
				if(cleared)cleared.remove();
				Object.keys(data.fragments).forEach(function(sel){
					document.querySelectorAll(sel).forEach(function(node){
						node.outerHTML=data.fragments[sel];
					});
				});
				/*
				 * added_to_cart AFTER the DOM is replaced, with Woo's own
				 * payload shape. cart-fragments.js updates its sessionStorage
				 * cache on this event, and side-carts hook it to open; firing
				 * wc_fragments_refreshed beforehand did neither, leaving the
				 * cached hash diverged and costing a refresh round trip on the
				 * next page load.
				 */
				if(window.jQuery){
					jQuery(document.body).trigger('added_to_cart',[data.fragments,data.cart_hash,jQuery(add)]);
				}
				add.classList.remove('is-busy');
				add.removeAttribute('aria-busy');
			})
			/* Not JSON (a sign-in page after the session ran out, a PHP notice
			   before the body) or no network: the line was not added - say so. */
			.catch(function(){fail(0);});
	});
	document.addEventListener('click',function(e){
		var step=e.target.closest('.beltoft-loop-qty__step');
		if(!step)return;
		e.preventDefault();
		var wrap=step.closest('.beltoft-loop-qty');
		var input=wrap&&wrap.querySelector('.beltoft-loop-qty__input');
		if(!input)return;
		var by=parseInt(step.dataset.step,10)||0;
		var min=parseInt(input.min,10); if(isNaN(min))min=1;
		var max=parseInt(input.max,10);
		var next=(parseInt(input.value,10)||min)+by;
		if(next<min)next=min;
		if(!isNaN(max)&&next>max)next=max;
		input.value=next;
		sync(input);
	});
})();
