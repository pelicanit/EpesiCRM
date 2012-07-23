<div style="max-width:900px">
{foreach from=$sections key=sk item=s}
	<div class="epesi_label header" style="clear:both;">{$s.header}</div>
		{foreach key=key item=button from=$s.buttons}
			{$__link.sections.$sk.buttons.$key.link.open}
				<div class="epesi_big_button bigger" style="float:left;">
					{if isset($button.icon)}
						<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
					{/if}
					<span>
						{$__link.sections.$sk.buttons.$key.link.text}
					</span>
				</div>
			{$__link.sections.$sk.buttons.$key.link.close}
		{/foreach}
	</tr>
{/foreach}
</div>