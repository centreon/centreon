import DOMPurify from 'dompurify';
import parse from 'html-react-parser';

const Comment =
  (classes) =>
  ({ comment }: { comment: string }): JSX.Element => {
    return (
      <span className={classes.comment}>
        {parse(DOMPurify.sanitize(comment))}
      </span>
    );
  };

export default Comment;
