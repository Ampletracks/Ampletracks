<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Role name',0,'Name')?>:
	</div>
	<div class="answer">
		<? formTextBox('role_name',10,200); ?>
        <? inputError('role_name'); ?>
	</div>
</div>
<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Role permissions',0,'Permissions')?>:
	</div>
	<div class="rolePermissions answer">
		<table class="permissions main">
			<thead>
				<th>Entity</th>
				<th>Permission</th>
				<th>Level</th>
				<th></th>
			</thead>
			<tbody>

			</tbody>
			<tfoot>
				<?
				global $validPermissions, $recordTypeSelect;
				$entitySelect = array_filter($validPermissions,function($key){return strpos($key,'recordTypeId:')!==0;},ARRAY_FILTER_USE_KEY);

				array_walk($entitySelect,function(&$v,$k){ $v=fromCamelCase($k); });
				$entitySelect = array_flip($entitySelect);
				$entitySelect['--- Record Types ---']='';
				$entitySelect = array_merge($entitySelect,$recordTypeSelect);

				echo '<td>';
				// Attach all these inputs to a non-existant form so they don't get submitted
				formOptionbox('entity',$entitySelect,'form="deliberatelyErroneous"');
				echo '</td>';
				// Add an empty permissions entry so that when we save we can tell the difference between
				// not wanting to update the permissions at all, and wanting to remove all existing permissions
				echo '<input type="hidden" name="permissions[]" value="" />';
				foreach($validPermissions as $entity=>$permissions) {
					$cleanedEntity = str_replace(':','_',$entity);
					printf('<td dependsOn="entity eq %s">',$entity);
					$action = explode('|',$permissions[0]);
					formOptionbox('action',array_combine($action,$action),'id="action_'.$cleanedEntity.'" form="deliberatelyErroneous"');
					printf('</td><td dependsOn="entity eq %s">',$entity);
					$level = explode('|',$permissions[1]);
					formOptionbox('level',array_combine($level,$level),'form="deliberatelyErroneous" dependsOn="action_'.$cleanedEntity.' !eq create"');
					printf('</td><td dependsOn="entity eq %s">',$entity);
					echo '<button class="add">Add</button>';
					echo '</td>';
				}
				?>
			</tfoot>
		</table>
	</div>
</div>
<script>
	<?
	global $impliedActions,$impliedActions_reversed,$currentPermissions;

	echo 'var impliedActions ='.json_encode($impliedActions).';';
	echo 'var impliedActions_reversed ='.json_encode($impliedActions_reversed).';';
	echo 'var entityNameLookup ='.json_encode(array_flip(array_filter($entitySelect))).';';
	echo 'var permissions ='.json_encode($currentPermissions).';';
	?>

	var permissionsTable = $('table.permissions tbody');

	function updatePermissions() {
		let thePermissions = Object.keys(permissions);
		thePermissions = thePermissions.sort(function(a,b){
			a = a.split(',');
			b = b.split(',');
			a = a[0].padEnd(20,' ') + ['delete','create','edit','view','list'].indexOf(a[2]) + ['global','project','own'].indexOf(a[1]);
			b = b[0].padEnd(20,' ') + ['delete','create','edit','view','list'].indexOf(b[2]) + ['global','project','own'].indexOf(b[1]);
			if (a==b) return 0;
			return a<b?-1:1;
		});

		permissionsTable.find('tr').remove();
		for( let details of thePermissions ) {
			let entity, level, action, displayLevel;
			[ entity, level, action ] = details.split(',');
			displayLevel = level;
			if (action=='create') displayLevel = '-';

			permissionsTable.append($(`
				<tr>
					<input type="hidden" name="permissions[]" value="${entity},${level},${action}">
					<td>${entityNameLookup[entity]}</td>
					<td>${action}</td>
					<td>${displayLevel}</td>
					<td><button class="delete">x</button></td>
				</tr>
			`).data('permission',details));
		}
	}

	function addPermission( entity,level,action ) {
		let added = false;
		for( let impliedAction of impliedActions[action].split(',')) {
			// see if they already have this permission at a higher level
			if (level=='own' && (permissions[entity+',project,'+impliedAction] || permissions[entity+',global,'+impliedAction])) continue;
			if (level=='project' && permissions[entity+',global,'+impliedAction]) continue;
			// We set it to entity+','+level below to help the logic in removePermissions
			permissions[entity+','+level+','+impliedAction]=entity+','+level;
			// remove the permission if they have it at a lower level
			if ((level=='project' || level=='global') && permissions[entity+',own,'+impliedAction]) delete permissions[entity+',own,'+impliedAction];
			if (level=='global' && (permissions[entity+',project,'+impliedAction])) delete permissions[entity+',project,'+impliedAction];
			added = true;
		}
		return added;
	}

	function getKeysByValue(obj,value) {
		return Object.keys(obj).filter(k=>obj[k]===value);
	}

	function removePermission( entity,level,action ) {
		let toReadd=[];
		for( let impliedAction of impliedActions_reversed[action].split(',')) {
			delete permissions[entity+','+level+','+impliedAction];
		}
		// if they have the permission at a lower level for this entity then re-add them
		// This is because those lower level permissions might imply some other permissions
		// that weren't previously included because they were superseded by the permission we just removed
		if (level=='project' || level=='global') toReadd.push( ...getKeysByValue(permissions,entity+',own') );
		if (level=='global') toReadd.push( ...getKeysByValue(permissions,entity+',project') );
		for( let toAdd of toReadd) {
			addPermission(...toAdd.split(','));
		}
	}

	$('table.permissions').on('click','button.add',function(){
		let self = $(this).closest('tr');
		let level = self.find('select[name=level]:visible').val();
		if (!level) level='global';
		let action = self.find('select[name=action]:visible').val();
		let entitySelect = self.find('select[name=entity]');
		let entity = entitySelect.val();
		let entityName = entitySelect.find('option:selected').text();

		if (permissions[entity+','+level+','+action]) $.alertable.alert('The role already has that permission');
		else {
			if (!addPermission(entity,level,action)) $.alertable.alert('That permission is already implied by the permissions above');
			else updatePermissions();
		}
		return false;
	});

	$('table.permissions').on('click','button.delete',function(){
		let self = $(this).closest('tr');

		let entity, level, action;
		[ entity, level, action ] = self.data('permission').split(',');
		removePermission( entity,level,action );
		updatePermissions();
		return false;
	});

	$(updatePermissions)
</script>