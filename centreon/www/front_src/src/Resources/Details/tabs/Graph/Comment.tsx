import { dateTimeFormat, useLocaleDateTimeFormat } from "@centreon/ui";
import { Button } from "@centreon/ui/components";
import { Tooltip, Typography } from "@mui/material";
import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import useAclQuery from "../../../Actions/Resource/aclQuery";
import AddCommentForm from "../../../Graph/Performance/Graph/AddCommentForm";
import {
	labelActionNotPermitted,
	labelAddComment,
} from "../../../translatedLabels";
import { useChartGraphStyles } from "./chartGraph.styles";

const Comment = ({ resource, commentDate, hideAddCommentTooltip }) => {
	const { classes } = useChartGraphStyles();
	const { t } = useTranslation();
	const queryClient = useQueryClient();
	const [addingComment, setAddingComment] = useState(false);
	const { format } = useLocaleDateTimeFormat();

	const { canComment } = useAclQuery();

	const prepareAddComment = (): void => {
		setAddingComment(true);
	};

	const isCommentPermitted = canComment([resource]);
	const commentTitle = isCommentPermitted ? "" : t(labelActionNotPermitted);

	const retriveTimeLineEvents = () => {
		queryClient.invalidateQueries({ queryKey: ["timeLineEvents"] });
		setAddingComment(false);
		hideAddCommentTooltip();
	};

	return (
		<>
			<div className={classes.commentContainer}>
				<Typography variant="body1" align="center">
					{format({
						date: new Date(commentDate as Date),
						formatString: dateTimeFormat,
					})}
				</Typography>
				<Tooltip title={commentTitle}>
					<Button
						disabled={!isCommentPermitted}
						size="small"
						onClick={prepareAddComment}
						variant="ghost"
					>
						<Typography variant="body2">{t(labelAddComment)}</Typography>
					</Button>
				</Tooltip>
			</div>

			{addingComment && (
				<AddCommentForm
					date={commentDate as Date}
					resource={resource}
					onClose={(): void => {
						setAddingComment(false);
					}}
					onSuccess={retriveTimeLineEvents}
				/>
			)}
		</>
	);
};

export default Comment;
