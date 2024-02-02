import parse from 'html-react-parser';
import DOMPurify from 'dompurify';

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
