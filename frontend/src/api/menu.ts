import request from '@/utils/request';
import type { MenuListQuery, StoreMenuRequest, UpdateMenuRequest, MenuItem } from '@/types/api/menu';

export function getMenuList(params?: MenuListQuery) {
  return request<any, ApiResponse<MenuItem[]>>({ url: '/system/menus', method: 'get', params });
}

export function getMenuDetail(id: number) {
  return request<any, ApiResponse<MenuItem>>({ url: `/system/menus/${id}`, method: 'get' });
}

export function createMenu(data: StoreMenuRequest) {
  return request<any, ApiResponse<MenuItem>>({ url: '/system/menus', method: 'post', data });
}

export function updateMenu(id: number, data: UpdateMenuRequest) {
  return request<any, ApiResponse<null>>({ url: `/system/menus/${id}`, method: 'put', data });
}

export function deleteMenu(id: number) {
  return request<any, ApiResponse<null>>({ url: `/system/menus/${id}`, method: 'delete' });
}
