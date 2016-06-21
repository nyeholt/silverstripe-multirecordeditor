<%-- todo(jake): add nice classes here --%>
<div class="field {$ExtraClass} <% if not $isReadonly %>js-multirecordediting-field<% end_if %>">
	<div class="clear"></div>
	<% if $Actions %>
		<div class="multirecordeditingfield-actions js-multirecordediting-actions clearfix">
			$Actions
			<div class="multirecordeditingfield-loading js-multirecordediting-loading"></div>
		</div>
	<% end_if %>
	<div class="multirecordeditingfield-fields <% if not $isReadonly %>js-multirecordediting-list<% end_if %>">
		$Field
	</div>
	<div class="clear"></div>
	<%-- p>test template MutliRecordEditingField_holder.ss</p --%>
</div>