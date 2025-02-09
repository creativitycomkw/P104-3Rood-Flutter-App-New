abstract class ShippingServiceInterface{
  Future<dynamic> getShippingMethod(int? sellerId, String? type,String countryId);
  Future<dynamic> addShippingMethod(int? id, String? cartGroupId);

  Future<dynamic> getChosenShippingMethod();


}