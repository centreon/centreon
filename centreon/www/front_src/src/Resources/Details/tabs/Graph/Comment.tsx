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

const Comment = ({ resource, commentDate, hideAddCommentTooltip }) => {
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
		console.log("retrieve");
		queryClient.invalidateQueries({ queryKey: ["timeLineEvents"] });
		setAddingComment(false);
		hideAddCommentTooltip();
	};

	return (
		<>
			<Typography variant="caption">
				{format({
					date: new Date(commentDate as Date),
					formatString: dateTimeFormat,
				})}
			</Typography>
			<Tooltip title={commentTitle}>
				<div>
					<Button
						// color="primary"
						disabled={!isCommentPermitted}
						size="small"
						onClick={prepareAddComment}
					>
						{t(labelAddComment)}
					</Button>
				</div>
			</Tooltip>

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
