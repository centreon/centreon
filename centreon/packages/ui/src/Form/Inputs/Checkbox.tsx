import { Box } from "@mui/material";

import { type FormikValues, useFormikContext } from "formik";
import { path, split } from "ramda";
import type { ChangeEvent } from "react";

import { useMemoComponent } from "../..";
import { Checkbox as CheckboxComponent } from "../../Checkbox";

import type { InputPropsWithoutGroup } from "./models";

const Checkbox = ({
	checkbox,
	fieldName,
	getDisabled,
	hideInput,
	dataTestId,
	label,
}: InputPropsWithoutGroup): JSX.Element => {
	const { values, setFieldValue } = useFormikContext<FormikValues>();

	const fieldNamePath = split(".", fieldName);

	const value = path(fieldNamePath, values);

	const disabled = getDisabled?.(values) || false;
	const hideCheckbox = hideInput?.(values) || false;

	const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
		const newValue = {
			...value,
			checked: event.target.checked,
			label: event.target.id,
		};

		setFieldValue(fieldName, newValue);
	};

	return useMemoComponent({
		Component: hideCheckbox ? (
			<Box />
		) : (
			<CheckboxComponent
				Icon={value?.Icon}
				checked={value?.checked}
				dataTestId={dataTestId || ""}
				disabled={disabled}
				label={label}
				labelPlacement={checkbox?.labelPlacement || "end"}
				onChange={handleChange}
			/>
		),
		memoProps: [value, disabled, hideCheckbox],
	});
};

export default Checkbox;
