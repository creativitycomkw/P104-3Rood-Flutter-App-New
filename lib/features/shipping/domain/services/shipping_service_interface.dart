abstract class ShippingServiceInterface{
  Future<dynamic> getShippingMethod(int? sellerId, String? type,{String countryId='1'});

  Future<dynamic> addShippingMethod(int? id, String? cartGroupId);

  Future<dynamic> getChosenShippingMethod();


}