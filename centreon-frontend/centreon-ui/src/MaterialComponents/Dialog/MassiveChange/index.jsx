import React from 'react';
import Button from '@material-ui/core/Button';
import DialogContentText from '@material-ui/core/DialogContentText';

import Dialog from '..';

function MassiveChangeDialog({
	onNoClicked,
	onYesClicked,
	children,
	info,
	applyLabel = "Apply",
	cancelLabel = "Cancel",
	...rest
}) {
	const Body = (
		<DialogContentText>
			{info}
			{children}
		</DialogContentText>
	);

	const Actions = (
		<React.Fragment>
			<Button
				variant="contained"
				color="primary"
				onClick={onYesClicked}
			>
				{applyLabel}
          </Button>

			<Button variant="outlined" onClick={onNoClicked} color="primary">
				{cancelLabel}
        	</Button>
		</React.Fragment>
	);
	return <Dialog body={Body} actions={Actions} {...rest} />;
}

export default MassiveChangeDialog;
