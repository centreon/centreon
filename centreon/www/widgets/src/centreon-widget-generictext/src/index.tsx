/*
FIX ME: The code of this widget is a placeholder.
The widget is not dynamically imported through Module Federation but imported directly by Centreon Web,
because, we have an issue by dislpaying a RichTextEditor (using lexical) in Centreon Web and another one
dynamically imported. Both are displayed but when we interact with them, Lexical throws an error because
Lexical doesn't seems to know which editor is active. The issue on lexical will be created soon and reported in
this comment.
For the moment, we only keep the module federation configuration and the widget properties JSON files.
*/

const GenericText = (): JSX.Element => {
  return <div />;
};

export default GenericText;
