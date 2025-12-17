import { Skeleton, type SkeletonProps } from "@mui/material";

const LoadingSkeleton = (props: SkeletonProps): JSX.Element => {
	return <Skeleton animation="wave" variant="rectangular" {...props} />;
};

export default LoadingSkeleton;
