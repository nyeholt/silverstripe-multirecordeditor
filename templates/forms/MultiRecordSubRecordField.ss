<div class="multirecordfield-subrecordfield {$extraClass} <% if not $isReadonly %>js-multirecordfield-list-item<% end_if %>" data-name="$Name" data-id="$DataID">
	<% if $CanSort %>
		<div class="multirecordfield-subrecordfield_sort">
			<span class="multirecordfield-subrecordfield_sort_handle js-multirecordfield-sort-handle">
				<span class="multirecordfield-subrecordfield_sort_handle_icon">Sort</span>
			</span>
		</div>
	<% end_if %>
	<div class="multirecordfield-subrecordfield_fields">
		<% if $ToggleCompositeField %>
			<% with $ToggleCompositeField %>
				<%-- NOTE(Jake): Must ensure $FieldHolder is called so JS is loaded in for frontend fields --%>
				$FieldHolder
			<% end_with %>
		<% else %>
			<% if $FieldList %>
				<% loop $FieldList %>
					$FieldHolder
				<% end_loop %>
			<% end_if %>
		<% end_if %>
	</div>
</div>