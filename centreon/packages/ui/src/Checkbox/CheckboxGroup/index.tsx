import FormGroup, { type FormGroupProps } from "@mui/material/FormGroup";
import type { TypographyProps } from "@mui/material/Typography";
import { equals, includes } from "ramda";
import { useTranslation } from "react-i18next";
import { makeStyles } from "tss-react/mui";
import Checkbox, { type LabelPlacement } from "../Checkbox";

interface Props {
	className?: string;
	dataTestId?: string;
	direction?: "horizontal" | "vertical";
	disabled?: boolean;
	formGroupProps?: FormGroupProps;
	labelPlacement?: LabelPlacement;
	labelProps?: TypographyProps;
	onChange?: (e) => void;
	options: Array<string>;
	values: Array<string>;
}

const useStyles = makeStyles()((theme) => ({
	checkbox: {
		display: "flex",
		justifyContent: "center",
		minWidth: theme.spacing(10),
	},
	container: {
		display: "flex",
		gap: 1,
	},
}));

const CheckboxGroup = ({
	options,
	values,
	direction = "vertical",
	onChange,
	labelPlacement = "end",
	disabled = false,
	dataTestId,
	className,
	labelProps,
	formGroupProps,
}: Props): JSX.Element => {
	const { classes, cx } = useStyles();

	const { t } = useTranslation();

	const row = !equals(direction, "vertical");

	return (
		<FormGroup
			classes={{ root: classes.container }}
			data-testid={dataTestId || ""}
			row={row}
			{...formGroupProps}
		>
			{options.map((value) => {
				return (
					<Checkbox
						checked={includes(value, values)}
						className={cx(classes.checkbox, className)}
						disabled={disabled}
						key={value}
						label={t(value)}
						labelPlacement={labelPlacement}
						labelProps={labelProps}
						onChange={onChange}
						id={value}
					/>
				);
			})}
		</FormGroup>
	);
};

export default CheckboxGroup;
