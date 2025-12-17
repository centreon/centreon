import AddCircleIcon from "@mui/icons-material/AddCircle";
import { isNil } from "ramda";
import type { ReactElement } from "react";
import { useTranslation } from "react-i18next";
import {
	type SelectEntry,
	SingleConnectedAutocompleteField,
} from "../../../..";
import { IconButton } from "../../..";
import RoleSelectField from "../common/RoleSelectField";
import type { Endpoints, Labels } from "../models";
import ContactSwitch from "./ContactSwitch";
import { useShareInputStyles } from "./ShareInput.styles";
import useShareInput from "./useShareInput";

interface Props {
	endpoints: Endpoints;
	labels: Labels["add"];
	roles: Array<SelectEntry>;
}

const ShareInput = ({ labels, endpoints, roles }: Props): ReactElement => {
	const { t } = useTranslation();
	const { classes } = useShareInputStyles();

	const {
		selectedContact,
		getOptionDisabled,
		getEndpoint,
		selectContact,
		isContactGroup,
		selectedRole,
		getRenderedOptionText,
		setSelectedRole,
		add,
		changeIdValue,
	} = useShareInput(endpoints);

	return (
		<div>
			<ContactSwitch labels={labels} />
			<div className={classes.inputs}>
				<SingleConnectedAutocompleteField
					clearable
					fullWidth
					changeIdValue={changeIdValue}
					disableClearable={false}
					field="name"
					getEndpoint={getEndpoint}
					getRenderedOptionText={getRenderedOptionText}
					getOptionDisabled={getOptionDisabled}
					label={t(
						isContactGroup
							? t(labels.autocompleteContactGroup)
							: t(labels.autocompleteContact),
					)}
					queryKey={isContactGroup ? labels.contactGroup : labels.contact}
					value={selectedContact}
					onChange={selectContact}
				/>
				<RoleSelectField
					disabled={isNil(selectedContact)}
					roles={roles}
					testId="add_role"
					value={selectedRole}
					onChange={setSelectedRole}
				/>
				<IconButton
					data-testid="add"
					disabled={isNil(selectedContact)}
					icon={<AddCircleIcon />}
					size="small"
					onClick={add}
				/>
			</div>
		</div>
	);
};

export default ShareInput;
