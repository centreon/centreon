import { Skeleton } from "@mui/material";
import { ParentSize } from "../..";
import type { SingleBarProps } from "./models";
import ResponsiveSingleBar from "./ResponsiveSingleBar";

const SingleBar = ({ data, ...props }: SingleBarProps): JSX.Element | null => {
	if (!data) {
		return null;
	}

	return (
		<ParentSize>
			{({ width, height }) => {
				if (!height || !width) {
					return <Skeleton height={20} variant="rectangular" width="100%" />;
				}

				return (
					<ResponsiveSingleBar
						{...props}
						data={data}
						height={height}
						width={width}
					/>
				);
			}}
		</ParentSize>
	);
};

export default SingleBar;
