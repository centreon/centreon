import { ReactNode } from "react";

import { equals } from "ramda";
import { makeStyles } from "tss-react/mui";

import { Chip, ChipProps } from "@mui/material";
import useStyleTable from "../Listing/useStyleTable";
import { TableStyleAtom } from "../Listing/models";
import { getStatusColors } from "../utils/statuses";
import type { SeverityCode } from "../utils/statuses";

export type Props = {
  clickable?: boolean;
  label?: string | ReactNode;
  severityCode: SeverityCode;
  statusColumn?: boolean;
} & ChipProps;

interface StylesProps {
  data: TableStyleAtom["statusColumnChip"];
  severityCode: SeverityCode;
}

const useStyles = makeStyles<StylesProps>()(
  (theme, { severityCode, data }) => ({
    chip: {
      "&:hover": { ...getStatusColors({ severityCode, theme }) },
      ...getStatusColors({ severityCode, theme }),
      "& .MuiChip-label": {
        alignItems: "center",
        display: "flex",
        height: "100%",
        padding: 0,
      },
    },
    statusColumnContainer: {
      fontWeight: "bold",
      height: data.height,
      marginLeft: 1,
      minWidth: theme.spacing((data.width - 1) / 8),
    },
  })
);

const StatusChip = ({
  severityCode,
  label,
  clickable = false,
  statusColumn = false,
  className,
  ...rest
}: Props): JSX.Element => {
  const { dataStyle } = useStyleTable({});
  const { classes, cx } = useStyles({
    data: dataStyle.statusColumnChip,
    severityCode,
  });

  const lowerLabel = (name: string): string =>
    name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();

  return (
    <Chip
      className={cx(classes.chip, className, {
        [classes.statusColumnContainer]: statusColumn,
      })}
      clickable={clickable}
      label={
        equals(typeof label, "string") ? lowerLabel(label as string) : label
      }
      {...rest}
    />
  );
};

export { SeverityCode, getStatusColors };
export default StatusChip;
