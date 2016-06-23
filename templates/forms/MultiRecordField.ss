<div class="field {$ExtraClass} <% if not $isReadonly %>js-multirecordfield-field<% end_if %>">
	<div class="clear"></div>
	<% if $Actions %>
		<div class="multirecordfield-actions js-multirecordfield-actions clearfix">
			$Actions
			<div class="multirecordfield-loading js-multirecordfield-loading"></div>
		</div>
	<% end_if %>
	<div class="multirecordfield-fields <% if not $isReadonly %>js-multirecordfield-list<% end_if %>">
		<% if $Fields %>
			$Fields
		<% end_if %>
	</div>
	<div class="clear"></div>
</div>