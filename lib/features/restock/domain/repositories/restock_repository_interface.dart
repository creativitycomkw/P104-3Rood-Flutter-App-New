import 'package:flutter_ecommerce/interface/repo_interface.dart';

abstract class RestockRepositoryInterface<T> extends RepositoryInterface{

  Future<dynamic> reorder(String orderId);

  Future<dynamic> getRestockProductList(String offset, bool getAll);

  Future<dynamic> deleteRestockProduct(String? type, String? id);
}