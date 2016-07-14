<div class="field js-multirecordfield-field {$ExtraClass}">
	<div class="clear"></div>
	<div class="multirecordfield-errors-holder">
		<%-- JavaScript errors get printed here --%>
		<p class="multirecordfield-errors js-multirecordfield-errors"></p>
	</div>
	<div class="multirecordfield-deleted js-multirecordfield-deleted-list" style="display: none;">
		<%-- Deleted fields get stored here as an <input> to track what got deleted --%>
	</div>
	<% if $Actions %>
		<div class="multirecordfield-actions js-multirecordfield-actions clearfix">
			$Actions
			<div class="multirecordfield-loading js-multirecordfield-loading"></div>
		</div>
	<% end_if %>
	<div class="multirecordfield-fields js-multirecordfield-list">
		<% if $Fields %>
			$Fields
		<% end_if %>
	</div>
	<div class="clear"></div>
</div>