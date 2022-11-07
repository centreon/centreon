interface Content {
  [x: string]: string | undefined;
  content: string;
  wrapper: string;
}

declare module '*.scss' {
  const content: Content;
  export default content;
}

declare module '*.svg' {
  const content;
  export const ReactComponent;
  export default content;
}
<<<<<<< HEAD

declare module '*.png';
declare module '*.jpg';
=======
>>>>>>> centreon/dev-21.10.x
