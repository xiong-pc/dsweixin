declare interface PageQuery {
  pageNum: number;
  pageSize: number;
}

declare interface PageResult<T> {
  list: T[];
  total: number;
}

declare interface ApiResponse<T = any> {
  code: number;
  msg: string;
  data: T;
}

declare interface OptionType {
  label: string;
  value: string | number;
  children?: OptionType[];
}
