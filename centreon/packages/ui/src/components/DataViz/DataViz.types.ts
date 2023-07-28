export type Size = {
  height: number;
  width: number;
}

export type VizSize = Size & {
  margin?: { bottom: number; left: number; right: number; top: number };
};
