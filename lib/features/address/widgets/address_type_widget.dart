import 'package:flutter/material.dart';
import 'package:flutter_ecommerce/features/address/domain/models/address_model.dart';
import 'package:flutter_ecommerce/localization/language_constrants.dart';
import 'package:flutter_ecommerce/utill/custom_themes.dart';
import 'package:flutter_ecommerce/utill/dimensions.dart';
import 'package:flutter_ecommerce/utill/images.dart';

class AddressTypeWidget extends StatelessWidget {
  final AddressModel? address;
  const AddressTypeWidget({super.key, this.address});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Image.asset(
          address?.addressType == 'home'
              ? Images.homeImage
              : address!.addressType == 'office'
                  ? Images.bag
                  : Images.moreImage,
          color: Theme.of(context).textTheme.bodyLarge?.color,
          height: 30,
          width: 30),
      title: Text(
        address!.country == '2'
            ? "${getTranslated('area', context)!.toUpperCase()}:  ${address?.area!.name ?? ''}"
            :"${getTranslated('address_1', context)!.toUpperCase()}:  ${address!.city}",
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: textBold.copyWith(
          fontSize: Dimensions.fontSizeDefault,
        ),
      ),
      subtitle: address!.country == '2'
          ? Text(
              "${getTranslated('block', context)!.toUpperCase()}:  ${address?.city ?? ''}\n${getTranslated('street', context)!.toUpperCase()}:  ${address?.address??''}\n${getTranslated('zip', context)!.toUpperCase()}:  ${address!.zip??''}",
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
              ),
            )
          : Text(
               "${getTranslated('address_2', context)!.toUpperCase()}:  ${address?.address ?? ''}",
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeDefault,
              ),
            ),
    );
  }
}
