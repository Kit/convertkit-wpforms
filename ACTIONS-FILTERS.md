<h1>Filters</h1><table>
				<thead>
					<tr>
						<th>File</th>
						<th>Filter Name</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody><tr>
						<td colspan="3">../includes/class-integrate-convertkit-wpforms.php</td>
					</tr><tr>
						<td>&nbsp;</td>
						<td><a href="#be_convertkit_form_args"><code>be_convertkit_form_args</code></a></td>
						<td></td>
					</tr><tr>
						<td>&nbsp;</td>
						<td><a href="#be_convertkit_process_form"><code>be_convertkit_process_form</code></a></td>
						<td></td>
					</tr>
					</tbody>
				</table><h3 id="be_convertkit_form_args">
						be_convertkit_form_args
						<code>includes/class-integrate-convertkit-wpforms.php::316</code>
					</h3><h4>Parameters</h4>
					<table>
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Type</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody><tr>
							<td>$args</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr><tr>
							<td>$fields</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr><tr>
							<td>$form_data</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr>
						</tbody>
					</table><h4>Usage</h4>
<pre>
add_filter( 'be_convertkit_form_args', function( $args, $fields, $form_data ) {
	// ... your code here
	// Return value
	return $args;
}, 10, 3 );
</pre>
<h3 id="be_convertkit_process_form">
						be_convertkit_process_form
						<code>includes/class-integrate-convertkit-wpforms.php::320</code>
					</h3><h4>Parameters</h4>
					<table>
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Type</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody><tr>
							<td>true</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr><tr>
							<td>$fields</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr><tr>
							<td>$form_data</td>
							<td>Unknown</td>
							<td>N/A</td>
						</tr>
						</tbody>
					</table><h4>Usage</h4>
<pre>
add_filter( 'be_convertkit_process_form', function( true, $fields, $form_data ) {
	// ... your code here
	// Return value
	return true;
}, 10, 3 );
</pre>
<h1>Actions</h1>