import 'dart:convert';

import 'package:flutter_ecommerce/features/splash/domain/models/config_model.dart';

class AddressModel {
  int? id;
  String? contactPersonName;
  String? addressType;
  String? address;
  String? city;
  String? zip;
  String? phone;
  String? createdAt;
  String? updatedAt;
  String? state;
  String? country;
  String? latitude;
  String? longitude;
  bool? isBilling;
  String? guestId;
  String? email;
  Area? area;
  AddressModel(
      {this.id,
      this.contactPersonName,
      this.addressType,
      this.address,
      this.city,
      this.zip,
      this.phone,
      this.createdAt,
      this.updatedAt,
      this.state,
      this.country,
      this.latitude,
      this.longitude,
      this.isBilling,
      this.guestId,
      this.email,
      this.area});

  AddressModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    contactPersonName = json['contact_person_name'];
    addressType = json['address_type'];
    address = json['address'];
    city = json['city'];
    zip = json['zip'];
    phone = json['phone'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    state = json['state'];
    country = json['country'].toString();

    latitude = json['latitude'];
    longitude = json['longitude'];
    isBilling = json['is_billing'] ?? false;
    email = json['email'];
    area = json.containsKey('area_name')
        ? json['area_name']!='null'?Area.fromJson(jsonDecode(json['area_name']) ):null
        : Area(
            zoneId: "47",
            countryId: "114",
            name: "Abdullah Al Mubarak Al Sabah",
            name_ar: "عبدالله المبارك الصباح");
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['contact_person_name'] = contactPersonName;
    data['address_type'] = addressType;
    data['address'] = address;
    data['city'] = city;
    data['zip'] = zip;
    data['phone'] = phone;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['state'] = state;
    data['country'] = country;
    data['latitude'] = latitude;
    data['longitude'] = longitude;
    data['is_billing'] = isBilling;
    data['guest_id'] = guestId;
    data['email'] = email;
    data['area']=area!=null?area!.toJson():null;
    return data;
  }
}
